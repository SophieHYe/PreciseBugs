#
# FileBroker - FileBrokerService
# (c) 2010-2013 Jakub Zubielik <jakub.zubielik@nordea.com>
#

class FBService < Sinatra::Base
  # File status
  FAILED_TO_ARCHIVE_FILE				= 1
  FAILED_TO_COMPRESS_FILE				= 2
  FAILED_TO_DECOMPRESS_FILE			= 3
  FAILED_TO_DECRYPT_FILE				= 4
  FAILED_TO_DOWNLOAD_MD5_FILE	  = 5
  FAILED_TO_DOWNLOAD_FILE				= 6
  FAILED_TO_ENCRYPT_FILE				= 7
  FAILED_TO_REMOVE_FILE					= 8
  FAILED_TO_REMOVE_MD5_FILE			= 9
  FAILED_TO_UPLOAD_FILE					= 10
  FAILED_TO_UPLOAD_MD5_FILE			= 11
  FAILED_TO_VERIFY_MD5					= 12
  FILE_ARCHIVED									= 13
  FILE_COMPRESSED								= 14
  FILE_DECOMPRESSED							= 15
  FILE_DECRYPTED								= 16
  FILE_DOWNLOADED								= 17
  FILE_ENCRYPTED								= 18
  FILE_REMOVED									= 19
  FILE_SCANNED									= 20
  FILE_UPLOADED									= 21
  INTERNAL_SYSTEM_ERROR         = 22
  MALICIOUS_CODE_DETECTED				= 23
  MD5_FILE_DOWNLOADED						= 24
  MD5_FILE_REMOVED							= 25
  MD5_FILE_UPLOADED							= 26
  MD5_VERIFIED									= 27
  TRANSFER_COMPLETED						= 28
  TRANSFER_SCHEDULED						= 29
  FILE_ENCODED									= 30
  FAILED_TO_ENCODE_FILE					= 31
  FAILED_TO_CALCULATE_MD5				= 32

  # Transfer status
  TRANSFER_RUNNING                = 33
  TRANSFER_COMPLETED_SUCCESSFULLY = 34
  TRANSFER_COMPLETED_WITH_ERRORS  = 35

  class StopTransfer < StandardError
  end

  module SoapFault
    class MustUnderstandError < StandardError
      def fault_code
        'MustUnderstand'
      end
    end

    class ClientError < StandardError
      def fault_code
        'Client'
      end
    end
  end

  class CleanUpTemporaryFiles < StandardError
  end

  class InternalSystemError < StandardError
  end

  set :show_exceptions, false
  set :root, "#{File.dirname(__FILE__)}/.."

  configure do
    mime_type :xml, 'text/xml'
  end

  def initialize(*args)
    GC.enable

    @avs	= AVScanner.new
    @db   = Database.new
    @xsd 	= Nokogiri::XML::Schema(File.read("#{File.dirname(__FILE__)}/../public/filebroker_service.xsd"))
    @xslt = Nokogiri::XSLT(File.read("#{File.dirname(__FILE__)}/soap_body.xslt"))
    @sys	= System.new
    @gpg	= GPG.new
    @threads = []
    @stderr_mutex = Mutex.new

    @fb_shutdown = false
    do_quit = Proc.new {
      @fb_shutdown = true
      @threads.each { |t| t.join }
      sleep 0.5 while @threads.length > 0
      Rack::Handler::WEBrick.shutdown
      File.delete("#{File.dirname(__FILE__)}/../tmp/filebroker.pid")
    }

    Signal.trap('SIGTERM', do_quit)
    Signal.trap('SIGQUIT', do_quit)
    Signal.trap('SIGINT',  do_quit)
    Signal.trap('CLD', 'IGNORE')

    super
  end

  # SOAP endpoint
  post '/filebroker_service' do
    begin

      GC.enable
      GC.start

      soap_message = Nokogiri::XML(request.body.read)
      soap_body = @xslt.transform(soap_message)
      errors = @xsd.validate(soap_body).map{ |e| e.message }.join(', ')
      raise(SoapFault::ClientError, errors) unless errors == ''

      if @db.select_configuration('debug') == 'true'
        log_msg = soap_message.to_s
        log_msg = log_msg.gsub(/assword>.+<\/.*assword>/, "#{$1}******#{$2}")              if log_msg =~ /(assword>).+(<\/.*assword>)/
        log_msg = log_msg.to_s.gsub(/PasswordDigest">.+<\/.*assword>/, "#{$1}******#{$2}") if log_msg =~ /(PasswordDigest">).+(<\/.*assword>)/
        log_msg = log_msg.to_s.gsub(/PasswordText">.+<\/.*assword>/, "#{$1}******#{$2}")   if log_msg =~ /(PasswordText">).+(<\/.*assword>)/

        log_path = "#{File.dirname(__FILE__)}/../log/#{soap_operation_to_method(soap_body).to_s.gsub(/^do_/, '')}/#{Time.new.strftime("%Y-%m-%d")}/"
        FileUtils.mkdir_p(log_path) if !Dir.exist?(log_path)
        log_name = "#{soap_operation_to_method(soap_body).to_s.gsub(/^do_/, '')}-#{DateTime.parse(Time.now.to_s)}-#{Digest::MD5.hexdigest(soap_body.to_s + rand(Time.now().to_i).to_s)}.log"
        File.open("#{log_path}/#{log_name}", 'w') { |log| log.puts log_msg }
      end

      if soap_message.root.at_xpath('//soap:Header/*[@soap:mustUnderstand="1" and not(@soap:actor)]', 'soap' => 'http://schemas.xmlsoap.org/soap/envelope/')
        raise(SoapFault::MustUnderstandError, 'SOAP Must Understand Error', 'MustUnderstand')
      end


      auth_type = @db.select_configuration('auth')

      if auth_type != 'none'
        prefix 	 = soap_message.root.namespace.prefix
        login 	 = soap_message.root.xpath("//#{prefix}:Envelope/#{prefix}:Header/wsse:Security/wsse:UsernameToken/wsse:Username").text
        password = soap_message.root.xpath("//#{prefix}:Envelope/#{prefix}:Header/wsse:Security/wsse:UsernameToken/wsse:Password").text

        client = {}
        client['login'] = login
        client = @db.get_client(client)
        client = @db.get_client_acl(client)
        raise(SoapFault::ClientError, 'not authorized') if [login, Digest::MD5.hexdigest(password).to_s] != [client['login'], client['password']]

        got_access = false
        @db.select_client_acl(client['client_id']).each { |ace|
          ace_desc = @db.select_client_acl_desc(ace['ace_id'])
          got_access = true if 'do_' + ace_desc.to_s == soap_operation_to_method(soap_body).to_s
        }
        raise(SoapFault::ClientError, 'method not allowed') if !got_access
      end

      if @fb_shutdown
        builder(:fault, :locals => { :fault_string => 'Service unavailable', :fault_code => 'Server' })
      else
        @stderr_mutex.lock
        STDERR.puts "- -> Received request message type: #{soap_operation_to_method(soap_body).to_s}"
        @stderr_mutex.unlock
        self.send(soap_operation_to_method(soap_body), soap_body)
      end
    rescue
      err_msg = "#{$!.backtrace.join("\n")}\n#{$!.message}\n"
      @stderr_mutex.try_lock
      80.times { STDERR.print '-' }
      STDERR.puts
      STDERR.puts "Exception time: #{DateTime.parse(Time.now.to_s)}"
      STDERR.puts err_msg
      80.times { STDERR.print '-' }
      STDERR.puts
      @stderr_mutex.unlock

      fault_code = $!.respond_to?(:fault_code) ? $!.fault_code : 'Server'
      builder(:fault, :locals => { :fault_string => err_msg, :fault_code => fault_code })
    end
  end

  # WSDL endpoint
  get '/filebroker_service' do
    if params.keys.first. != nil and params.keys.first.downcase == 'wsdl'
      url = ENV['BASE_URL'] || "http://#{request.env['SERVER_NAME']}:#{request.port}"
      url = ENV['BASE_URL'] || "https://#{request.env['SERVER_NAME']}:#{request.port}" if @db.select_configuration('ssl') == 'true'
      erb(:filebroker_service_wsdl, :locals => { :url => url }, :content_type => :xml)
    else
      builder(:fault, :locals => { :fault_string => 'unknown parameter', :fault_code => 'Client' })
    end
  end

  private

  # Detect the SOAP operation based on the root element in the SOAP body
  def soap_operation_to_method(soap_body)
    method = ('do_' + soap_body.root.name.sub(/Request$/, '').gsub(/([A-Z]+)([A-Z][a-z])/,'\1_\2').gsub(/([a-z\d])([A-Z])/,'\1_\2')).downcase.to_sym
  end

  # Transfer operation, send back transfer ID
  def do_collection_transfer(soap_body)
    begin
      prefix = soap_body.root.namespace.prefix

      transfer = {}
      transfer['transfer_id']                   = Digest::MD5.hexdigest(soap_body.to_s + rand(Time.now().to_i).to_s)

      transfer['source'] = {}
      transfer['source']['protocol'] 	          = soap_body.xpath("//#{prefix}:Source/#{prefix}:Protocol/text()").to_s.downcase
      transfer['source']['address'] 	          = soap_body.xpath("//#{prefix}:Source/#{prefix}:Address/text()").to_s
      transfer['source']['port'] 		            = soap_body.xpath("//#{prefix}:Source/#{prefix}:Port/text()").to_s
      transfer['source']['login'] 	            = soap_body.xpath("//#{prefix}:Source/#{prefix}:Login/text()").to_s
      transfer['source']['password'] 	          = soap_body.xpath("//#{prefix}:Source/#{prefix}:Password/text()").to_s
      transfer['source']['path'] 		            = soap_body.xpath("//#{prefix}:Source/#{prefix}:Path/text()").to_s
      transfer['source']['passive'] 	          = soap_body.xpath("//#{prefix}:Source/#{prefix}:PassiveMode/text()").to_s
      transfer['source']['binary'] 	            = soap_body.xpath("//#{prefix}:Source/#{prefix}:BinaryMode/text()").to_s
      transfer['source']['text'] 		            = soap_body.xpath("//#{prefix}:Source/#{prefix}:TextMode/text()").to_s
      transfer['source']['presite'] 	          = soap_body.xpath("//#{prefix}:Source/#{prefix}:PreSite/text()").to_s
      transfer['source']['postsite'] 	          = soap_body.xpath("//#{prefix}:Source/#{prefix}:PostSite/text()").to_s

      transfer['source']['files'] = []
      soap_body.xpath("//#{prefix}:Source/#{prefix}:Files").each { |node|
        node.xpath("//#{prefix}:File").each { |x|
          transfer['source']['files'] << x.text.to_s
        }
      }

      transfer['target'] = {}
      transfer['target']['protocol'] 	          = soap_body.xpath("//#{prefix}:Target/#{prefix}:Protocol/text()").to_s.downcase
      transfer['target']['address'] 	          = soap_body.xpath("//#{prefix}:Target/#{prefix}:Address/text()").to_s
      transfer['target']['port'] 		            = soap_body.xpath("//#{prefix}:Target/#{prefix}:Port/text()").to_s
      transfer['target']['login'] 	            = soap_body.xpath("//#{prefix}:Target/#{prefix}:Login/text()").to_s
      transfer['target']['password'] 	          = soap_body.xpath("//#{prefix}:Target/#{prefix}:Password/text()").to_s
      transfer['target']['path'] 		            = soap_body.xpath("//#{prefix}:Target/#{prefix}:Path/text()").to_s
      transfer['target']['passive'] 	          = soap_body.xpath("//#{prefix}:Target/#{prefix}:PassiveMode/text()").to_s
      transfer['target']['binary'] 	            = soap_body.xpath("//#{prefix}:Target/#{prefix}:BinaryMode/text()").to_s
      transfer['target']['text'] 		            = soap_body.xpath("//#{prefix}:Target/#{prefix}:TextMode/text()").to_s
      transfer['target']['presite'] 	          = soap_body.xpath("//#{prefix}:Target/#{prefix}:PreSite/text()").to_s
      transfer['target']['postsite'] 	          = soap_body.xpath("//#{prefix}:Target/#{prefix}:PostSite/text()").to_s

      transfer['options'] = {}
      transfer['options']['archive']	 			    = soap_body.xpath("//#{prefix}:Options/#{prefix}:Archive/text()").to_s
      transfer['options']['avscanning'] 			  = soap_body.xpath("//#{prefix}:Options/#{prefix}:AVScanning/text()").to_s
      transfer['options']['compression'] 			  = soap_body.xpath("//#{prefix}:Options/#{prefix}:Compression/text()").to_s
      transfer['options']['decompression'] 		  = soap_body.xpath("//#{prefix}:Options/#{prefix}:Decompression/text()").to_s
      transfer['options']['remove_source_file'] = soap_body.xpath("//#{prefix}:Options/#{prefix}:RemoveSourceFile/text()").to_s
      transfer['options']['md5']	 				      = soap_body.xpath("//#{prefix}:Options/#{prefix}:MD5/text()").to_s
      transfer['options']['direct_upload'] 		  = soap_body.xpath("//#{prefix}:Options/#{prefix}:DirectUpload/text()").to_s
      transfer['options']['filename_suffix'] 		= soap_body.xpath("//#{prefix}:Options/#{prefix}:FilenameSuffix/text()").to_s
      transfer['options']['dec_method']         = soap_body.xpath("//#{prefix}:Options/#{prefix}:Decryption/#{prefix}:Method/text()").to_s.downcase
      transfer['options']['dec_key_id']         = soap_body.xpath("//#{prefix}:Options/#{prefix}:Decryption/#{prefix}:KeyID/text()").to_s
      transfer['options']['enc_method']         = soap_body.xpath("//#{prefix}:Options/#{prefix}:Encryption/#{prefix}:Method/text()").to_s.downcase
      transfer['options']['enc_key_id']         = soap_body.xpath("//#{prefix}:Options/#{prefix}:Encryption/#{prefix}:KeyID/text()").to_s
      transfer['options']['encoding_from'] 			= soap_body.xpath("//#{prefix}:Options/#{prefix}:Encoding/#{prefix}:From/text()").to_s
      transfer['options']['encoding_to'] 			  = soap_body.xpath("//#{prefix}:Options/#{prefix}:Encoding/#{prefix}:To/text()").to_s

      transfer['options']['enc_recipients'] = []
      soap_body.xpath("//#{prefix}:Options/#{prefix}:Encryption").each { |node|
        node.xpath("//#{prefix}:Recipient").each { |x|
          transfer['options']['enc_recipients'] << x.text.to_s
        }
      }

      transfer['source']['files'].sort! { |x, y| String.natcmp(x, y)}

      @threads << Thread.new {
        begin
          err = File.open("log/transfer/#{transfer['transfer_id']}.log", 'w')
          err.sync = true
          err.puts "Trace file for transfer request: #{transfer['transfer_id']}"
          err.puts "Transfer started at: #{Time.now.to_s}"

          #
          # Add unknown transfer endpoints
          #

          begin
            transfer['source'] = @db.select_account(transfer['source'])
          rescue Database::AccountNotExist
            transfer['source'] = @db.insert_account(transfer['source'])
          end

          begin
            transfer['target'] = @db.select_account(transfer['target'])
          rescue Database::AccountNotExist
            transfer['target'] = @db.insert_account(transfer['target'])
          end


          #
          # Set initial transfer status
          #

          transfer_id = @db.insert_transfer(transfer['transfer_id'],
                                            transfer['source']['account_id'],
                                            transfer['target']['account_id'],
                                            transfer['source']['path'],
                                            transfer['target']['path'])

          @db.insert_transfer_status(transfer_id, FBService::TRANSFER_RUNNING, DateTime.now)


          #
          # Set initial file status
          #

          @db.insert_file_status(transfer_id, transfer['options']['md5'].split('/').last, FBService::TRANSFER_SCHEDULED, DateTime.now) if transfer['options']['md5'] != ''
          transfer['source']['files'].each { |file| @db.insert_file_status(transfer_id, file, FBService::TRANSFER_SCHEDULED, DateTime.now) }


          #
          # Download files
          #

          files_download  = []
          if transfer['source']['protocol'] == 'cifs'
            src_conn = Connector::CIFS.new
            src_conn.address 	  = transfer['source']['address']
            src_conn.port 		  = transfer['source']['port']
            src_conn.login 		  = transfer['source']['login']
            src_conn.password 	= transfer['source']['password']
            src_conn.share 	    = transfer['source']['path'].split('/')[1]

            begin
              src_conn.connect
              src_conn.cd(transfer['source']['path'])
              Dir.mkdir("process/#{transfer['transfer_id']}", 0700)
            rescue
              transfer['source']['files'].each { |file| @db.update_file_status(transfer_id, file, FBService::FAILED_TO_DOWNLOAD_FILE, DateTime.now) }
              raise FBService::StopTransfer, $!
            end

            if transfer['options']['md5'] != ''
              begin
                src_conn.get(transfer['options']['md5'], "process/#{transfer['transfer_id']}/#{transfer['options']['md5'].split('/').last}")
                @db.update_file_status(transfer_id, transfer['options']['md5'].split('/').last, FBService::MD5_FILE_DOWNLOADED, DateTime.now)
              rescue
                err.puts "Exception raised at: #{Time.now.to_s}"
                err.puts $!.backtrace
                err.puts $!.message
                @db.update_file_status(transfer_id, transfer['options']['md5'].split('/').last, FBService::FAILED_TO_DOWNLOAD_MD5_FILE, DateTime.now)
                raise FBService::StopTransfer, $!
              end
            end

            transfer['source']['files'].each { |file|
              begin
                src_conn.get("#{transfer['source']['path']}/#{file}", "process/#{transfer['transfer_id']}/#{file}")
                files_download << file
                @db.update_file_status(transfer_id, file, FBService::FILE_DOWNLOADED, DateTime.now)
              rescue
                err.puts "Exception raised at: #{Time.now.to_s}"
                err.puts $!.backtrace
                err.puts $!.message
                @db.update_file_status(transfer_id, file, FBService::FAILED_TO_DOWNLOAD_FILE, DateTime.now)
                File.unlink("process/#{transfer['transfer_id']}/#{file}") if File.exist?("process/#{transfer['transfer_id']}/#{file}")
              end
            }

            src_conn.disconnect
          elsif transfer['source']['protocol'] == 'ftp'
            src_conn = Connector::FTP.new
            src_conn.address 	= transfer['source']['address']
            src_conn.port 		= transfer['source']['port']
            src_conn.login 		= transfer['source']['login']
            src_conn.password = transfer['source']['password']
            src_conn.passive 	= transfer['source']['passive']
            src_conn.binary 	= transfer['source']['binary']
            src_conn.text 		= transfer['source']['text']
            src_conn.presite 	= transfer['source']['presite']
            src_conn.postsite = transfer['source']['postsite']

            begin
              src_conn.connect
              Dir.mkdir("process/#{transfer['transfer_id']}", 0700)
            rescue
              transfer['source']['files'].each { |file| @db.update_file_status(transfer_id, file, FBService::FAILED_TO_DOWNLOAD_FILE, DateTime.now) }
              raise FBService::StopTransfer, $!
            end

            if transfer['options']['md5'] != ''
              begin
                src_conn.get(transfer['options']['md5'], "process/#{transfer['transfer_id']}/#{transfer['options']['md5'].split('/').last}")
                @db.update_file_status(transfer_id, transfer['options']['md5'].split('/').last, FBService::MD5_FILE_DOWNLOADED, DateTime.now)
              rescue
                @db.update_file_status(transfer_id, transfer['options']['md5'].split('/').last, FBService::FAILED_TO_DOWNLOAD_MD5_FILE, DateTime.now)
                raise FBService::StopTransfer, $!
              end
            end

            transfer['source']['files'].each { |file|
              begin
                src_conn.get("#{transfer['source']['path']}/#{file}", "process/#{transfer['transfer_id']}/#{file}")
                files_download << file
                @db.update_file_status(transfer_id, file, FBService::FILE_DOWNLOADED, DateTime.now)
              rescue
                err.puts "Exception raised at: #{Time.now.to_s}"
                err.puts $!.backtrace
                err.puts $!.message
                @db.update_file_status(transfer_id, file, FBService::FAILED_TO_DOWNLOAD_FILE, DateTime.now)
                File.unlink("process/#{transfer['transfer_id']}/#{file}") if File.exist?("process/#{transfer['transfer_id']}/#{file}")
              end
            }

            src_conn.disconnect
          elsif transfer['source']['protocol'] == 'sftp'
            begin
              src_conn = Connector::SFTP.new
              src_conn.address 	= transfer['source']['address']
              src_conn.port 		= transfer['source']['port']
              src_conn.login 		= transfer['source']['login']
              src_conn.password = transfer['source']['password']

              begin
                src_conn.connect
                Dir.mkdir("process/#{transfer['transfer_id']}", 0700)
              rescue
                transfer['source']['files'].each { |file| @db.update_file_status(transfer_id, file, FBService::FAILED_TO_DOWNLOAD_FILE, DateTime.now) }
                raise FBService::StopTransfer, $!
              end

              if transfer['options']['md5'] != ''
                begin
                  src_conn.get(transfer['options']['md5'], "process/#{transfer['transfer_id']}/#{transfer['options']['md5'].split('/').last}")
                  @db.update_file_status(transfer_id, transfer['options']['md5'].split('/').last, FBService::MD5_FILE_DOWNLOADED, DateTime.now)
                rescue
                  @db.update_file_status(transfer_id, transfer['options']['md5'].split('/').last, FBService::FAILED_TO_DOWNLOAD_MD5_FILE, DateTime.now)
                  raise FBService::StopTransfer, $!
                end
              end

              transfer['source']['files'].each { |file|
                begin
                  src_conn.get("#{transfer['source']['path']}/#{file}", "process/#{transfer['transfer_id']}/#{file}")
                  files_download << file
                  @db.update_file_status(transfer_id, file, FBService::FILE_DOWNLOADED, DateTime.now)
                rescue
                  err.puts "Exception raised at: #{Time.now.to_s}"
                  err.puts $!.backtrace
                  err.puts $!.message
                  @db.update_file_status(transfer_id, file, FBService::FAILED_TO_DOWNLOAD_FILE, DateTime.now)
                  File.unlink("process/#{transfer['transfer_id']}/#{file}") if File.exist?("process/#{transfer['transfer_id']}/#{file}")
                end
              }

              src_conn.disconnect
            rescue
              @db.update_file_status(transfer_id, file, FBService::FAILED_TO_DOWNLOAD_FILE, DateTime.now)
              raise FBService::InternalSystemError, $!.to_s
            end
          end


          #
          # Process files
          #

          files_remove = files_download.dup
          files_download.each { |file|

            #
            # Archive files
            #

            if transfer['options']['archive'] == 'true'
              begin
                @sys.archive(transfer['transfer_id'], file)
                @db.update_file_status(transfer_id, file, FBService::FILE_ARCHIVED, DateTime.now)
              rescue
                err.puts "Exception raised at: #{Time.now.to_s}"
                err.puts $!.backtrace
                err.puts $!.message
                @db.update_file_status(transfer_id, file, FBService::FAILED_TO_ARCHIVE_FILE, DateTime.now)
                File.unlink("process/#{transfer['transfer_id']}/#{file}")
              end
            end


            #
            # Verify files
            #

            if transfer['options']['md5'] != ''
              begin
                src_md5 = ''
                cur_md5 = Digest::MD5.new()

                File.open("process/#{transfer['transfer_id']}/#{transfer['options']['md5'].split('/').last}", 'r').readlines.each { |l|
                  if l =~ /^(\S{32})\s+#{file}$/
                    src_md5 = $1
                    break
                  end
                }

                File.open("process/#{transfer['transfer_id']}/#{file}", 'r').each_line { |l| cur_md5 << l }
                raise System::IncorrectMD5, "incorrect md5 for file '#{file}'" if cur_md5.hexdigest != src_md5
                @db.update_file_status(transfer_id, file, FBService::MD5_VERIFIED, DateTime.now)
              rescue
                err.puts "Exception raised at: #{Time.now.to_s}"
                err.puts $!.backtrace
                err.puts $!.message
                @db.update_file_status(transfer_id, file, FBService::FAILED_TO_VERIFY_MD5, DateTime.now)
                File.unlink("process/#{transfer['transfer_id']}/#{file}")
                files_remove.delete_if { |t| t == file }
                next
              end
            end


            #
            # Decrypt files
            #

            if transfer['options']['dec_method'] == 'gpg' or transfer['options']['dec_method'] == 'pgp'
              begin
                @gpg.decrypt("process/#{transfer['transfer_id']}/#{file}")
                @db.update_file_status(transfer_id, file, FBService::FILE_DECRYPTED, DateTime.now)
                if file =~ /^(.+)\.([gpg|pgp|asc]{3})$/
                  file = $1
                end
              rescue
                err.puts "Exception raised at: #{Time.now.to_s}"
                err.puts $!.backtrace
                err.puts $!.message
                @db.update_file_status(transfer_id, file, FBService::FAILED_TO_DECRYPT_FILE, DateTime.now)
                File.unlink("process/#{transfer['transfer_id']}/#{file}") if File.exist?("process/#{transfer['transfer_id']}/#{file}")
                files_remove.delete_if { |t| t == file }
                next
              end
            elsif transfer['options']['dec_method'] =~ /des-blb/
              begin
                key = File.open("etc/keys/#{transfer['options']['dec_key_id']}.key", 'r').readlines.first.strip
                @sys.exec("sbin/des-blb-i386 -D -u -k '#{key}' process/#{transfer['transfer_id']}/#{file} process/#{transfer['transfer_id']}/#{file}.dec")
                File.rename("process/#{transfer['transfer_id']}/#{file}.dec", "process/#{transfer['transfer_id']}/#{file}")
              rescue
                err.puts "Exception raised at: #{Time.now.to_s}"
                err.puts $!.backtrace
                err.puts $!.message
                @db.update_file_status(transfer_id, file, FBService::FAILED_TO_DECRYPT_FILE, DateTime.now)
                File.unlink("process/#{transfer['transfer_id']}/#{file}.dec") if File.exist?("process/#{transfer['transfer_id']}/#{file}.dec")
                File.unlink("process/#{transfer['transfer_id']}/#{file}") if File.exist?("process/#{transfer['transfer_id']}/#{file}")
                files_remove.delete_if { |t| t == file }
                next
              end
            end


            #
            # Decompress files
            #

            if transfer['options']['decompression'] == 'true'
              begin
                @sys.decompress(transfer['transfer_id'], file)
                @db.update_file_status(transfer_id, file, FBService::FILE_DECOMPRESSED, DateTime.now)
              rescue
                err.puts "Exception raised at: #{Time.now.to_s}"
                err.puts $!.backtrace
                err.puts $!.message
                @db.update_file_status(transfer_id, file, FBService::FAILED_TO_DECOMPRESS_FILE, DateTime.now)
                File.unlink("process/#{transfer['transfer_id']}/#{file}") if File.exist?("process/#{transfer['transfer_id']}/#{file}")
                files_remove.delete_if { |t| t == file }
                next
              end
            end
          }


          #
          # Remove old MD5 file
          #

          if transfer['options']['md5'] != ''
            begin
              File.unlink("process/#{transfer['transfer_id']}/#{transfer['options']['md5'].split('/').last}")
            rescue
              err.puts "Exception raised at: #{Time.now.to_s}"
              err.puts $!.backtrace
              err.puts $!.message
            end
          end


          #
          # Process files
          #


          files_process = Dir.entries("process/#{transfer['transfer_id']}").sort { |x, y| String.natcmp(x, y)}
          files_process.each { |file|
            next if File.directory?(file)

            #
            # AV scanning
            #

            if transfer['options']['avscanning'] == 'true'
              begin
                @avs.scan("process/#{transfer['transfer_id']}/#{file}")
                @db.update_file_status(transfer_id, file, FBService::FILE_SCANNED, DateTime.now)
              rescue
                err.puts "Exception raised at: #{Time.now.to_s}"
                err.puts $!.backtrace
                err.puts $!.message
                @db.update_file_status(transfer_id, file, FBService::MALICIOUS_CODE_DETECTED, DateTime.now)
                File.unlink("process/#{transfer['transfer_id']}/#{file}")
                next
              end
            end


            # File encoding
            if transfer['options']['encoding_from'] != '' and transfer['options']['encoding_to'] != ''
              begin
                @sys.iconv("process/#{transfer['transfer_id']}/#{file}", transfer['options']['encoding_from'], transfer['options']['encoding_to'], true)
                @db.update_file_status(transfer_id, file, FBService::FILE_ENCODED, DateTime.now)
              rescue
                err.puts "Exception raised at: #{Time.now.to_s}"
                err.puts $!.backtrace
                err.puts $!.message
                @db.update_file_status(transfer_id, file, FBService::FAILED_TO_ENCODE_FILE, DateTime.now)
                File.unlink("process/#{transfer['transfer_id']}/#{file}")
                next
              end
            end
          }


          #
          # Compress files
          #

          if transfer['options']['compression'] != ''
            files_compress = Dir.entries("process/#{transfer['transfer_id']}")
            files_compress.each { |file|
              next if File.directory?(file)

              begin
                @sys.compress(transfer['transfer_id'], file, transfer['options']['compression'])
                @db.update_file_status(transfer_id, file, FBService::FILE_COMPRESSED, DateTime.now)
              rescue
                err.puts "Exception raised at: #{Time.now.to_s}"
                err.puts $!.backtrace
                err.puts $!.message
                @db.update_file_status(transfer_id, file, FBService::FAILED_TO_COMPRESS_FILE, DateTime.now)
                File.unlink("process/#{transfer['transfer_id']}/#{file}")
                next
              end
            }
          end


          #
          # Encrypt files
          #

          if transfer['options']['enc_method'] == 'gpg' or transfer['options']['enc_method'] == 'pgp'
            files_compress = Dir.entries("process/#{transfer['transfer_id']}")
            files_compress.each { |file|
              next if File.directory?(file)
              next if file == transfer['options']['md5'].split('/').last

              begin
                @gpg.encrypt("process/#{transfer['transfer_id']}/#{file}", transfer['options']['enc_recipients'])
                @db.update_file_status(transfer_id, file, FBService::FILE_ENCRYPTED, DateTime.now)
              rescue
                err.puts "Exception raised at: #{Time.now.to_s}"
                err.puts $!.backtrace
                err.puts $!.message
                @db.update_file_status(transfer_id, file, FBService::FAILED_TO_ENCRYPT_FILE, DateTime.now)
                File.unlink("process/#{transfer['transfer_id']}/#{file}")
                files_remove.delete_if { |t| t == file }
                next
              end
            }
          elsif transfer['options']['enc_method'] =~ /des/
            files_compress = Dir.entries("process/#{transfer['transfer_id']}")
            files_compress.each { |file|
              next if File.directory?(file)
              next if file == transfer['options']['md5'].split('/').last

              begin
                @sys.exec("openssl enc -e -#{transfer['options']['enc_method']} -kfile etc/keys/#{transfer['options']['enc_key_id']}.key -in process/#{transfer['transfer_id']}/#{file} -out process/#{transfer['transfer_id']}/#{file}.enc")
                File.rename("process/#{transfer['transfer_id']}/#{file}.enc", "process/#{transfer['transfer_id']}/#{file}")
              rescue
                err.puts "Exception raised at: #{Time.now.to_s}"
                err.puts $!.backtrace
                err.puts $!.message
                @db.update_file_status(transfer_id, file, FBService::FAILED_TO_ENCRYPT_FILE, DateTime.now)
                File.unlink("process/#{transfer['transfer_id']}/#{file}.enc") if File.exist?("process/#{transfer['transfer_id']}/#{file}.enc")
                File.unlink("process/#{transfer['transfer_id']}/#{file}") if File.exist?("process/#{transfer['transfer_id']}/#{file}")
                files_remove.delete_if { |t| t == file }
              end
            }
          end

          #
          # Recalculate MD5
          #

          if transfer['options']['md5'] != ''
            files_md5 = Dir.entries("process/#{transfer['transfer_id']}")
            files_md5.each { |file|
              next if File.directory?(file)
              next if file == transfer['options']['md5'].split('/').last

              begin
                cur_md5 = Digest::MD5.new()
                File.open("process/#{transfer['transfer_id']}/#{file}", 'r').each_line { |l| cur_md5 << l }

                File.open("process/#{transfer['transfer_id']}/#{transfer['options']['md5'].split('/').last}", 'a') { |f|
                  f << "#{cur_md5}  #{file}\n"
                }
              rescue
                err.puts "Exception raised at: #{Time.now.to_s}"
                err.puts $!.backtrace
                err.puts $!.message
                @db.update_file_status(transfer_id, file, FBService::FAILED_TO_CALCULATE_MD5, DateTime.now)
                File.unlink("process/#{transfer['transfer_id']}/#{file}")
                next
              end
            }
          end

          #
          # Upload files
          #

          files_upload = Dir.entries("process/#{transfer['transfer_id']}").sort { |x, y| String.natcmp(x, y)}
          files_upload.delete_at(files_upload.find_index(transfer['options']['md5'].split('/').last)) if transfer['options']['md5'] != ''


          if transfer['target']['protocol'] == 'cifs'
            trg_conn = Connector::CIFS.new
            trg_conn.address 	= transfer['target']['address']
            trg_conn.port 		= transfer['target']['port']
            trg_conn.login 		= transfer['target']['login']
            trg_conn.password = transfer['target']['password']
            trg_conn.share 	  = transfer['target']['path'].split('/')[1]

            begin
              trg_conn.connect
              trg_conn.cd(transfer['target']['path'])
            rescue
              files_upload.each { |file|
                next if File.directory?(file)
                @db.update_file_status(transfer_id, file, FBService::FAILED_TO_UPLOAD_FILE, DateTime.now)
              }
              raise FBService::StopTransfer, $!
            end

            files_upload.each { |file|
              next if File.directory?(file)

              begin
                if transfer['options']['direct_upload'] == 'true'
                  trg_conn.put("process/#{transfer['transfer_id']}/#{file}", "#{transfer['target']['path']}/#{file}") if transfer['options']['filename_suffix'] == ''
                  trg_conn.put("process/#{transfer['transfer_id']}/#{file}", "#{transfer['target']['path']}/#{file}#{transfer['options']['filename_suffix']}") if transfer['options']['filename_suffix'] != ''
                else
                  trg_conn.put("process/#{transfer['transfer_id']}/#{file}", "#{transfer['target']['path']}/#{file}.partial")
                  trg_conn.rename("#{transfer['target']['path']}/#{file}.partial", "#{transfer['target']['path']}/#{file}") if transfer['options']['filename_suffix'] == ''
                  trg_conn.rename("#{transfer['target']['path']}/#{file}.partial", "#{transfer['target']['path']}/#{file}#{transfer['options']['filename_suffix']}") if transfer['options']['filename_suffix'] != ''
                end

                @db.update_file_status(transfer_id, file, FBService::FILE_UPLOADED, DateTime.now)
              rescue
                err.puts "Exception raised at: #{Time.now.to_s}"
                err.puts $!.backtrace
                err.puts $!.message
                @db.update_file_status(transfer_id, file, FBService::FAILED_TO_UPLOAD_FILE, DateTime.now)
                File.unlink("process/#{transfer['transfer_id']}/#{file}") if File.exist?("process/#{transfer['transfer_id']}/#{file}")
                files_remove.delete_if { |t| t == file }
                files_remove.delete_if { |t| t == file.gsub(/\.gpg/, '') }
                next
              end
            }


            if transfer['options']['md5'] != '' and files_remove.length > 0
              begin
                if transfer['options']['direct_upload'] == 'true'
                  if transfer['options']['filename_suffix'] == ''
                    trg_conn.put("process/#{transfer['transfer_id']}/#{transfer['options']['md5'].split('/').last}",
                                 "#{transfer['target']['path']}/#{transfer['options']['md5'].split('/').last}")
                  else
                    trg_conn.put("process/#{transfer['transfer_id']}/#{transfer['options']['md5'].split('/').last}",
                                 "#{transfer['target']['path']}/#{transfer['options']['md5'].split('/').last}#{transfer['options']['filename_suffix']}")
                  end
                else
                  if transfer['options']['filename_suffix'] == ''
                    trg_conn.put("process/#{transfer['transfer_id']}/#{transfer['options']['md5'].split('/').last}",
                                 "#{transfer['target']['path']}/#{transfer['options']['md5'].split('/').last}.partial")
                    trg_conn.rename("#{transfer['target']['path']}/#{transfer['options']['md5'].split('/').last}.partial",
                                    "#{transfer['target']['path']}/#{transfer['options']['md5'].split('/').last}")
                  else
                    trg_conn.put("process/#{transfer['transfer_id']}/#{transfer['options']['md5'].split('/').last}",
                                 "#{transfer['target']['path']}/#{transfer['options']['md5'].split('/').last}.partial")
                    trg_conn.rename("#{transfer['target']['path']}/#{transfer['options']['md5'].split('/').last}.partial",
                                    "#{transfer['target']['path']}/#{transfer['options']['md5'].split('/').last}#{transfer['options']['filename_suffix']}")
                  end
                end
                @db.update_file_status(transfer_id, transfer['options']['md5'].split('/').last, FBService::MD5_FILE_UPLOADED, DateTime.now)
              rescue
                err.puts "Exception raised at: #{Time.now.to_s}"
                err.puts $!.backtrace
                err.puts $!.message
                @db.update_file_status(transfer_id, transfer['options']['md5'].split('/').last, FBService::FAILED_TO_UPLOAD_MD5_FILE, DateTime.now)
              end
            end

            trg_conn.disconnect
          elsif transfer['target']['protocol'] == 'ftp'
            trg_conn = Connector::FTP.new
            trg_conn.address 	= transfer['target']['address']
            trg_conn.port 		= transfer['target']['port']
            trg_conn.login 		= transfer['target']['login']
            trg_conn.password = transfer['target']['password']
            trg_conn.passive 	= transfer['target']['passive']
            trg_conn.binary 	= transfer['target']['binary']
            trg_conn.text 		= transfer['target']['text']
            trg_conn.presite 	= transfer['target']['presite']
            trg_conn.postsite = transfer['target']['postsite']

            begin
              trg_conn.connect
            rescue
              files_upload.each { |file|
                next if File.directory?(file)
                @db.update_file_status(transfer_id, file, FBService::FAILED_TO_UPLOAD_FILE, DateTime.now)
              }
              raise FBService::StopTransfer, $!
            end

            files_upload.each { |file|
              next if File.directory?(file)

              begin
                if transfer['options']['direct_upload'] == 'true'
                  # EXCEPTION FOR MVS - OMIT PATH AND PUT FILENAME IN ''
                  #trg_conn.put("process/#{transfer['transfer_id']}/#{file}", "#{transfer['target']['path']}/#{file}") if transfer['options']['filename_suffix'] == ''
                  #if transfer['options']['filename_suffix'] != ''
                  #  trg_conn.put("process/#{transfer['transfer_id']}/#{file}", "#{transfer['target']['path']}/#{file}#{transfer['options']['filename_suffix']}")
                  #end
                  trg_conn.put("process/#{transfer['transfer_id']}/#{file}", "'#{file}'") if transfer['options']['filename_suffix'] == ''
                  if transfer['options']['filename_suffix'] != ''
                    trg_conn.put("process/#{transfer['transfer_id']}/#{file}", "'#{file}#{transfer['options']['filename_suffix']}'")
                  end
                else
                  trg_conn.put("process/#{transfer['transfer_id']}/#{file}", "#{transfer['target']['path']}/#{file}.partial")
                  trg_conn.rename("#{transfer['target']['path']}/#{file}.partial", "#{transfer['target']['path']}/#{file}") if transfer['options']['filename_suffix'] == ''
                  if transfer['options']['filename_suffix'] != ''
                    trg_conn.rename("#{transfer['target']['path']}/#{file}.partial", "#{transfer['target']['path']}/#{file}#{transfer['options']['filename_suffix']}")
                  end
                end

                @db.update_file_status(transfer_id, file, FBService::FILE_UPLOADED, DateTime.now)
              rescue
                err.puts "Exception raised at: #{Time.now.to_s}"
                err.puts $!.backtrace
                err.puts $!.message
                @db.update_file_status(transfer_id, file, FBService::FAILED_TO_UPLOAD_FILE, DateTime.now)
                File.unlink("process/#{transfer['transfer_id']}/#{file}") if File.exist?("process/#{transfer['transfer_id']}/#{file}")
                files_remove.delete_if { |t| t == file }
                files_remove.delete_if { |t| t == file.gsub(/\.gpg/, '') }
                next
              end
            }

            if transfer['options']['md5'] != '' and files_remove.length > 0
              begin
                if transfer['options']['direct_upload'] == 'true'
                  if transfer['options']['filename_suffix'] == ''
                    trg_conn.put("process/#{transfer['transfer_id']}/#{transfer['options']['md5'].split('/').last}",
                                 "#{transfer['target']['path']}/#{transfer['options']['md5'].split('/').last}")
                  else
                    trg_conn.put("process/#{transfer['transfer_id']}/#{transfer['options']['md5'].split('/').last}",
                                 "#{transfer['target']['path']}/#{transfer['options']['md5'].split('/').last}#{transfer['options']['filename_suffix']}")
                  end
                else
                  if transfer['options']['filename_suffix'] == ''
                    trg_conn.put("process/#{transfer['transfer_id']}/#{transfer['options']['md5'].split('/').last}",
                                 "#{transfer['target']['path']}/#{transfer['options']['md5'].split('/').last}.partial")
                    trg_conn.rename("#{transfer['target']['path']}/#{transfer['options']['md5'].split('/').last}.partial",
                                    "#{transfer['target']['path']}/#{transfer['options']['md5'].split('/').last}")
                  else
                    trg_conn.put("process/#{transfer['transfer_id']}/#{transfer['options']['md5'].split('/').last}",
                                 "#{transfer['target']['path']}/#{transfer['options']['md5'].split('/').last}.partial")
                    trg_conn.rename("#{transfer['target']['path']}/#{transfer['options']['md5'].split('/').last}.partial",
                                    "#{transfer['target']['path']}/#{transfer['options']['md5'].split('/').last}#{transfer['options']['filename_suffix']}")
                  end
                end
                @db.update_file_status(transfer_id, transfer['options']['md5'].split('/').last, FBService::MD5_FILE_UPLOADED, DateTime.now)
              rescue
                err.puts "Exception raised at: #{Time.now.to_s}"
                err.puts $!.backtrace
                err.puts $!.message
                @db.update_file_status(transfer_id, transfer['options']['md5'].split('/').last, FBService::FAILED_TO_UPLOAD_MD5_FILE, DateTime.now)
              end
            end

            trg_conn.disconnect
          elsif transfer['target']['protocol'] == 'sftp'
            trg_conn = Connector::SFTP.new
            trg_conn.address 	= transfer['target']['address']
            trg_conn.port 		= transfer['target']['port']
            trg_conn.login 		= transfer['target']['login']
            trg_conn.password = transfer['target']['password']

            begin
              trg_conn.connect
            rescue
              files_upload.each { |file|
                next if File.directory?(file)
                @db.update_file_status(transfer_id, file, FBService::FAILED_TO_UPLOAD_FILE, DateTime.now)
              }
              raise FBService::StopTransfer, $!
            end

            files_upload.each { |file|
              next if File.directory?(file)

              begin
                if transfer['options']['direct_upload'] == 'true'
                  trg_conn.put("process/#{transfer['transfer_id']}/#{file}", "#{transfer['target']['path']}/#{file}") if transfer['options']['filename_suffix'] == ''
                  if transfer['options']['filename_suffix'] != ''
                    trg_conn.put("process/#{transfer['transfer_id']}/#{file}", "#{transfer['target']['path']}/#{file}#{transfer['options']['filename_suffix']}")
                  end
                else
                  trg_conn.put("process/#{transfer['transfer_id']}/#{file}", "#{transfer['target']['path']}/#{file}.partial")
                  trg_conn.rename("#{transfer['target']['path']}/#{file}.partial", "#{transfer['target']['path']}/#{file}") if transfer['options']['filename_suffix'] == ''
                  if transfer['options']['filename_suffix'] != ''
                    trg_conn.rename("#{transfer['target']['path']}/#{file}.partial", "#{transfer['target']['path']}/#{file}#{transfer['options']['filename_suffix']}")
                  end
                end

                @db.update_file_status(transfer_id, file, FBService::FILE_UPLOADED, DateTime.now)
              rescue
                err.puts "Exception raised at: #{Time.now.to_s}"
                err.puts $!.backtrace
                err.puts $!.message.to_s
                @db.update_file_status(transfer_id, file, FBService::FAILED_TO_UPLOAD_FILE, DateTime.now)
                File.unlink("process/#{transfer['transfer_id']}/#{file}") if File.exist?("process/#{transfer['transfer_id']}/#{file}")
                files_remove.delete_if { |t| t == file }
                files_remove.delete_if { |t| t == file.gsub(/\.gpg/, '') }
                next
              end
            }

            if transfer['options']['md5'] != '' and files_remove.length > 0
              begin
                if transfer['options']['direct_upload'] == 'true'
                  if transfer['options']['filename_suffix'] == ''
                    trg_conn.put("process/#{transfer['transfer_id']}/#{transfer['options']['md5'].split('/').last}",
                                 "#{transfer['target']['path']}/#{transfer['options']['md5'].split('/').last}")
                  else
                    trg_conn.put("process/#{transfer['transfer_id']}/#{transfer['options']['md5'].split('/').last}",
                                 "#{transfer['target']['path']}/#{transfer['options']['md5'].split('/').last}#{transfer['options']['filename_suffix']}")
                  end
                else
                  if transfer['options']['filename_suffix'] == ''
                    trg_conn.put("process/#{transfer['transfer_id']}/#{transfer['options']['md5'].split('/').last}",
                                 "#{transfer['target']['path']}/#{transfer['options']['md5'].split('/').last}.partial")
                    trg_conn.rename("#{transfer['target']['path']}/#{transfer['options']['md5'].split('/').last}.partial",
                                    "#{transfer['target']['path']}/#{transfer['options']['md5'].split('/').last}")
                  else
                    trg_conn.put("process/#{transfer['transfer_id']}/#{transfer['options']['md5'].split('/').last}",
                                 "#{transfer['target']['path']}/#{transfer['options']['md5'].split('/').last}.partial")
                    trg_conn.rename("#{transfer['target']['path']}/#{transfer['options']['md5'].split('/').last}.partial",
                                    "#{transfer['target']['path']}/#{transfer['options']['md5'].split('/').last}#{transfer['options']['filename_suffix']}")
                  end
                end
                @db.update_file_status(transfer_id, transfer['options']['md5'].split('/').last, FBService::MD5_FILE_UPLOADED, DateTime.now)
              rescue
                err.puts "Exception raised at: #{Time.now.to_s}"
                err.puts $!.backtrace
                err.puts $!.message.to_s
                @db.update_file_status(transfer_id, transfer['options']['md5'].split('/').last, FBService::FAILED_TO_UPLOAD_MD5_FILE, DateTime.now)
              end
            end

            trg_conn.disconnect
          end


          #
          # Removing source files
          #

          if transfer['options']['remove_source_file'] == 'true'
            if transfer['source']['protocol'] == 'cifs'
              src_conn = Connector::CIFS.new
              src_conn.address 	= transfer['source']['address']
              src_conn.port 		= transfer['source']['port']
              src_conn.login 		= transfer['source']['login']
              src_conn.password = transfer['source']['password']
              src_conn.share 	  = transfer['source']['path'].split('/')[1]

              begin
                src_conn.connect
                src_conn.cd(transfer['source']['path'])
              rescue
                files_remove.each { |file|
                  next if File.directory?(file)
                  @db.update_file_status(transfer_id, file, FBService::FAILED_TO_REMOVE_FILE, DateTime.now)
                }
                raise FBService::StopTransfer, $!
              end

              files_remove.each { |file|
                begin
                  src_conn.remove("#{transfer['source']['path']}/#{file}")
                  @db.update_file_status(transfer_id, file, FBService::FILE_REMOVED, DateTime.now)
                rescue
                  err.puts "Exception raised at: #{Time.now.to_s}"
                  err.puts $!.backtrace
                  err.puts $!.message.to_s
                  @db.update_file_status(transfer_id, file, FBService::FAILED_TO_REMOVE_FILE, DateTime.now)
                  next
                end
              }

              if transfer['options']['md5'] != '' and files_remove.length > 0
                begin
                  src_conn.remove(transfer['options']['md5'])
                  @db.update_file_status(transfer_id, File.split(transfer['options']['md5']).last, FBService::MD5_FILE_REMOVED, DateTime.now)
                rescue
                  err.puts "Exception raised at: #{Time.now.to_s}"
                  err.puts $!.backtrace
                  err.puts $!.message.to_s
                  @db.update_file_status(transfer_id, File.split(transfer['options']['md5']).last, FBService::FAILED_TO_REMOVE_MD5_FILE, DateTime.now)
                end
              end

              src_conn.disconnect
            elsif transfer['source']['protocol'] == 'ftp'
              src_conn = Connector::FTP.new
              src_conn.address 	= transfer['source']['address']
              src_conn.port 		= transfer['source']['port']
              src_conn.login 		= transfer['source']['login']
              src_conn.password = transfer['source']['password']

              begin
                src_conn.connect
              rescue
                files_remove.each { |file|
                  next if File.directory?(file)
                  @db.update_file_status(transfer_id, file, FBService::FAILED_TO_REMOVE_FILE, DateTime.now)
                }
                raise FBService::StopTransfer, $!
              end

              files_remove.each { |file|
                begin
                  src_conn.remove("#{transfer['source']['path']}/#{file}")
                  @db.update_file_status(transfer_id, file, FBService::FILE_REMOVED, DateTime.now)
                rescue
                  err.puts "Exception raised at: #{Time.now.to_s}"
                  err.puts $!.backtrace
                  err.puts $!.message.to_s
                  @db.update_file_status(transfer_id, file, FBService::FAILED_TO_REMOVE_FILE, DateTime.now)
                  next
                end
              }

              if transfer['options']['md5'] != '' and files_remove.length > 0
                begin
                  src_conn.remove(transfer['options']['md5'])
                  @db.update_file_status(transfer_id, File.split(transfer['options']['md5']).last, FBService::MD5_FILE_REMOVED, DateTime.now)
                rescue
                  err.puts "Exception raised at: #{Time.now.to_s}"
                  err.puts $!.backtrace
                  err.puts $!.message.to_s
                  @db.update_file_status(transfer_id, File.split(transfer['options']['md5']).last, FBService::FAILED_TO_REMOVE_MD5_FILE, DateTime.now)
                end
              end

              src_conn.disconnect
            elsif transfer['source']['protocol'] == 'sftp'
              src_conn = Connector::SFTP.new
              src_conn.address 	= transfer['source']['address']
              src_conn.port 		= transfer['source']['port']
              src_conn.login 		= transfer['source']['login']
              src_conn.password = transfer['source']['password']

              begin
                src_conn.connect
              rescue
                files_remove.each { |file|
                  next if File.directory?(file)
                  @db.update_file_status(transfer_id, file, FBService::FAILED_TO_REMOVE_FILE, DateTime.now)
                }
                raise FBService::StopTransfer, $!
              end

              files_remove.each { |file|
                begin
                  src_conn.remove("#{transfer['source']['path']}/#{file}")
                  @db.update_file_status(transfer_id, file, FBService::FILE_REMOVED, DateTime.now)
                rescue
                  err.puts "Exception raised at: #{Time.now.to_s}"
                  err.puts $!.backtrace
                  err.puts $!.message.to_s
                  @db.update_file_status(transfer_id, file, FBService::FAILED_TO_REMOVE_FILE, DateTime.now)
                  next
                end
              }

              if transfer['options']['md5'] != '' and files_remove.length > 0
                begin
                  src_conn.remove(transfer['options']['md5'])
                  @db.update_file_status(transfer_id, File.split(transfer['options']['md5']).last, FBService::MD5_FILE_REMOVED, DateTime.now)
                rescue
                  err.puts "Exception raised at: #{Time.now.to_s}"
                  err.puts $!.backtrace
                  err.puts $!.message.to_s
                  @db.update_file_status(transfer_id, File.split(transfer['options']['md5']).last, FBService::FAILED_TO_REMOVE_MD5_FILE, DateTime.now)
                end
              end

              src_conn.disconnect
            end
          end

          @db.select_file_status(transfer_id).each { |file|
            if  file['status_id'].to_i == FBService::FILE_UPLOADED or
                file['status_id'].to_i == FBService::FILE_REMOVED or
                file['status_id'].to_i == FBService::MD5_FILE_UPLOADED or
                file['status_id'].to_i == FBService::MD5_FILE_REMOVED
              @db.update_file_status(transfer_id, file['filename'], FBService::TRANSFER_COMPLETED, DateTime.now)
            end
          }

          err.puts "Transfer finished at: #{Time.now.to_s}"
          err.close

          got_errors = false
          @db.select_file_status(transfer_id).each { |file|
            got_errors = true if  file['status_id'].to_i == FBService::FAILED_TO_ARCHIVE_FILE or
                file['status_id'].to_i == FBService::FAILED_TO_COMPRESS_FILE or
                file['status_id'].to_i == FBService::FAILED_TO_DECOMPRESS_FILE or
                file['status_id'].to_i == FBService::FAILED_TO_DECRYPT_FILE or
                file['status_id'].to_i == FBService::FAILED_TO_DOWNLOAD_MD5_FILE or
                file['status_id'].to_i == FBService::FAILED_TO_DOWNLOAD_FILE or
                file['status_id'].to_i == FBService::FAILED_TO_ENCRYPT_FILE or
                file['status_id'].to_i == FBService::FAILED_TO_REMOVE_FILE or
                file['status_id'].to_i == FBService::FAILED_TO_REMOVE_MD5_FILE or
                file['status_id'].to_i == FBService::FAILED_TO_UPLOAD_FILE or
                file['status_id'].to_i == FBService::FAILED_TO_UPLOAD_MD5_FILE or
                file['status_id'].to_i == FBService::FAILED_TO_VERIFY_MD5 or
                file['status_id'].to_i == FBService::MALICIOUS_CODE_DETECTED or
                file['status_id'].to_i == FBService::FAILED_TO_ENCODE_FILE or
                file['status_id'].to_i == FBService::FAILED_TO_CALCULATE_MD5
          }

          @db.update_transfer_status(transfer_id, FBService::TRANSFER_COMPLETED_SUCCESSFULLY, DateTime.now) if !got_errors
          @db.update_transfer_status(transfer_id, FBService::TRANSFER_COMPLETED_WITH_ERRORS,  DateTime.now) if  got_errors
        rescue
          err.puts "Exception raised at: #{Time.now.to_s}"
          err.puts $!.backtrace
          err.puts $!.message.to_s
          err.puts "Transfer finished at: #{Time.now.to_s}"
          err.close
          @db.update_transfer_status(transfer_id, FBService::TRANSFER_COMPLETED_WITH_ERRORS, DateTime.now)
        ensure
          FileUtils.rm_rf("process/#{transfer['transfer_id']}")
          @threads.delete_if { |x| x == Thread.current }
        end
      }

      builder(:collection_transfer_response, :locals => { :transfer_id => transfer['transfer_id'] })
    rescue StandardError => e
      err_msg = "#{$!.backtrace.join("\n")}\n#{$!.message}\n"
      @stderr_mutex.try_lock
      80.times { STDERR.print '-' }
      STDERR.puts
      STDERR.puts "Transfer ID: #{transfer['transfer_id']}"
      STDERR.puts "Exception time: #{DateTime.parse(Time.now.to_s)}"
      STDERR.puts err_msg
      80.times { STDERR.print '-' }
      STDERR.puts
      @stderr_mutex.unlock

      fault_code = e.respond_to?(:fault_code) ? e.fault_code : 'Server'
      builder(:fault, :locals => { :fault_string => e.message, :fault_code => fault_code })
    end
  end

  # Status operation, send back transfer status
  def do_collection_status(soap_body)
    begin
      prefix = soap_body.root.namespace.prefix
      transfer_id = soap_body.xpath("//#{prefix}:TransferID/text()").to_s

      status = @db.select_transfer_status(transfer_id)
      status['status_desc'] = @db.select_transfer_status_desc(status['status_id'])
      status['status_time'] = DateTime.parse(status['status_time'])

      files  = []
      @db.select_file_status(transfer_id).each { |file|
        file['status_desc'] = @db.select_file_status_desc(file['status_id'])
        file['status_type'] = @db.select_file_status_type(file['status_id'])
        file['status_time'] = DateTime.parse(file['status_time'])
        files << file
      }

      builder(:collection_status_response, :locals => { :transfer_id => transfer_id, :files => files, :status => status })
    rescue
      err_msg = "#{$!.backtrace.join("\n")}\n#{$!.message}\n"
      @stderr_mutex.try_lock
      80.times { STDERR.print '-' }
      STDERR.puts
      STDERR.puts "Exception time: #{DateTime.parse(Time.now.to_s)}"
      STDERR.puts err_msg
      80.times { STDERR.print '-' }
      STDERR.puts
      @stderr_mutex.unlock

      builder(:fault, :locals => { :fault_string => 'failed to get status', :fault_code => 'Server' })
    end
  end

  # List operation, send back remote directory list
  def do_list(soap_body)
    begin
      prefix = soap_body.root.namespace.prefix

      req = {}
      req['protocol'] = soap_body.xpath("//#{prefix}:Protocol/text()").to_s.downcase
      req['address'] 	= soap_body.xpath("//#{prefix}:Address/text()").to_s
      req['port'] 		= soap_body.xpath("//#{prefix}:Port/text()").to_s
      req['login'] 		= soap_body.xpath("//#{prefix}:Login/text()").to_s
      req['password'] = soap_body.xpath("//#{prefix}:Password/text()").to_s
      req['path'] 		= soap_body.xpath("//#{prefix}:Path/text()").to_s

      begin
        source = @db.select_account(req)
      rescue Database::AccountNotExist
        source = @db.insert_account(req)
      end

      list = []
      if req['protocol'] == 'cifs'
        conn = Connector::CIFS.new
        conn.address 	= req['address']
        conn.login 		= req['login']
        conn.password = req['password']
        conn.share 	  = req['path'].split('/')[1]
        conn.connect
        conn.cd(req['path'])
        list = conn.list(req['path'])
        conn.disconnect
      elsif req['protocol'] == 'ftp'
        conn = Connector::FTP.new
        conn.address 	= req['address']
        conn.port 		= req['port']
        conn.login 		= req['login']
        conn.password = req['password']
        conn.connect
        list = conn.list(req['path'])
        conn.disconnect
      elsif req['protocol'] == 'sftp'
        conn = Connector::SFTP.new
        conn.address 	= req['address']
        conn.port 		= req['port']
        conn.login 		= req['login']
        conn.password = req['password']
        conn.connect
        list = conn.list(req['path'])
        conn.disconnect
      end

      # Convert time format
      list.map { |j| j['mtime'] = DateTime.parse(j['mtime']) }

      # Remove currently transferred files
      @db.select_running_transfers_by_source(FBService::TRANSFER_RUNNING, source['account_id'], source['path']).each { |i|
        @db.select_transfer_files(i['transfer_id']).each { |j|
          list.delete_if { |k| k['name'] == j['filename'] }
        }
      }

      # Remove temporary files
      list.delete_if { |j| j['name'] =~ /\.partial$/ }

      builder(:list_response, :locals => { :list => list })
    rescue
      err_msg = "#{$!.backtrace.join("\n")}\n#{$!.message}\n"
      @stderr_mutex.try_lock
      80.times { STDERR.print '-' }
      STDERR.puts
      STDERR.puts "Exception time: #{DateTime.parse(Time.now.to_s)}"
      STDERR.puts err_msg
      80.times { STDERR.print '-' }
      STDERR.puts
      @stderr_mutex.unlock

      builder(:fault, :locals => { :fault_string => 'failed to list directory', :fault_code => 'Server' })
    end
  end

  # Status operation, send back transfer trace log
  def do_log(soap_body)
    begin
      prefix = soap_body.root.namespace.prefix
      transfer_id = soap_body.xpath("//#{prefix}:TransferID/text()").to_s
      log = File.open("#{File.dirname(__FILE__)}/../log/transfer/#{transfer_id}.log", 'r').readlines.join
      log = "\n<![CDATA[\n" + log + "]]>\n"
      builder(:log_response, :locals => { :transfer_id => transfer_id, :log => log })
    rescue
      err_msg = "#{$!.backtrace.join("\n")}\n#{$!.message}\n"
      @stderr_mutex.try_lock
      80.times { STDERR.print '-' }
      STDERR.puts
      STDERR.puts "Exception time: #{DateTime.parse(Time.now.to_s)}"
      STDERR.puts err_msg
      80.times { STDERR.print '-' }
      STDERR.puts
      @stderr_mutex.unlock

      builder(:fault, :locals => { :fault_string => 'failed to get log', :fault_code => 'Server' })
    end
  end

  # Service status operation, send back last transfer time
  def do_service_status(soap_body)
    begin
      time = 0
      @db.select_last_transfers(1).each { |t| time = DateTime.parse(t['status_time']) }

      builder(:service_status_response, :locals => { :time => time })
    rescue
      err_msg = "#{$!.backtrace.join("\n")}\n#{$!.message}\n"
      @stderr_mutex.try_lock
      80.times { STDERR.print '-' }
      STDERR.puts
      STDERR.puts "Exception time: #{DateTime.parse(Time.now.to_s)}"
      STDERR.puts err_msg
      80.times { STDERR.print '-' }
      STDERR.puts
      @stderr_mutex.unlock

      builder(:fault, :locals => { :fault_string => 'failed to get log', :fault_code => 'Server' })
    end
  end
end
