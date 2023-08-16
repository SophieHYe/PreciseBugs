#
# FileBroker - Common Library
# (c) 2010-2013 Jakub Zubielik <jakub.zubielik@nordea.com>
#

class AVScanner
  class AVScannerNotAvailable < StandardError
  end

  class AVScannerDatabaseNotAvailable < StandardError
  end

  class VirusDetected < StandardError
  end

  def initialize
    @sys = System.new
  end

  def scan(file)
    begin
      @sys.exec("clamscan -i \"#{file}\" 2>&1")
    rescue
      if $!.to_s.strip =~ /command not found/
        raise AVScannerNotAvailable, "av scanner is not available"
      elsif $!.to_s.strip =~ /No supported database files found in/
        raise AVScannerDatabaseNotAvailable 'av scanner database is not available'
      else
        $!.to_s.split("\n").each { |l|
          if l.strip =~ /^#{file.gsub(/\//, "\\\/")}\:\s(.+)\sFOUND/
            virus = $1
            raise VirusDetected, "virus detected: #{virus}"
          end
        }
      end
    end
  end
end

class Mail
  def Mail.send(options)
#   user     = options['user']
    from     = options['from']
    to       = options['to']
#    pass     = options['pass']
    server   = options['server']
    subject  = options['subject']
    port    = options['port']

    body =  "From: FES Admin <#{from}>\n"
    body << "To: #{to}\n"
    body << "Subject: #{subject}\n"
    body << "Date: #{Time.now}\n"
    body << "Importance:high\n"
    body << "MIME-Version:1.0\n"
    body << "\n\n\n"
    body << options['body']

    t = []
    t << Thread.new {
      begin
        s = TCPSocket.open(server, port)

        s.print "HELO localhost\r\n"
        s.recvfrom(1024)

        s.print "EHLO localhost\r\n"
        s.recvfrom(1024)

        s.print "MAIL FROM: #{from}\r\n"
        s.recvfrom(1024)

        s.print "RCPT TO: #{to}\r\n"
        s.recvfrom(1024)

        s.print "DATA\r\n"
        s.recvfrom(1024)

        s.print body


        s.print "\r\n.\r\n"
        s.recvfrom(1024)

        s.print "QUIT\r\n"
        s.recvfrom(1024)

        s.close
      rescue
        STDERR.puts $!.to_s
      end
    }
  end
end

class System
  class CommandExecutionError < StandardError
  end

  class CompressionFailed < StandardError
  end

  class DecompressionFailed < StandardError
  end

  class IncorrectMD5 < StandardError
  end

  class EncodingFailed < StandardError
  end

  class ArchivingFailed < StandardError
  end

  def initialize

  end

  def compress(transfer_id, file, level = 0)
    begin

      self.exec("zip -#{level} 'process/#{transfer_id}/files_#{transfer_id}.zip' 'process/#{transfer_id}/#{file}'")
      File.unlink("process/#{transfer_id}/#{file}") if File.exist?("process/#{transfer_id}/#{file}")
    rescue
      raise CompressionFailed, $!
    end
  end

  def decompress(transfer_id, file)
    begin

      if file =~ /\.tar\.bz2$/
        self.exec("tar jxf 'process/#{transfer_id}/#{file}' -C 'process/#{transfer_id}'")
      elsif file =~ /\.tar\.gz$/
        self.exec("tar zxf 'process/#{transfer_id}/#{file}' -C 'process/#{transfer_id}'")
      elsif file =~ /\.tar$/
        self.exec("tar xf 'process/#{transfer_id}/#{file}' -C 'process/#{transfer_id}'")
      elsif file =~ /\.bz2$/
        self.exec("bzip2 -d 'process/#{transfer_id}/#{file}'")
      elsif file =~ /\.gz$/
        self.exec("gzip -d 'process/#{transfer_id}/#{file}'")
      elsif file =~ /\.zip$/
        self.exec("unzip -o 'process/#{transfer_id}/#{file}' -d 'process/#{transfer_id}'")
      else
        raise DecompressionFailed, 'unknown archive type'
      end
      File.unlink("process/#{transfer_id}/#{file}") if File.exist?("process/#{transfer_id}/#{file}")
    rescue
      raise DecompressionFailed, $!
    end
  end

  def archive(transfer_id, file)
    begin
      self.exec("zip -5 'archive/#{transfer_id}' 'process/#{transfer_id}/#{file}'")
    rescue
      raise ArchivingFailed, $!
    end
  end

  def is_compressed(path)
    out = self.exec("file -b #{path}")
    return true if out =~ /bzip2/
    false
  end

  def verify_md5(file, file_md5)
    begin
      md5_local  = self.exec("md5sum \"#{file}\"").split(' ')
      md5_remote = self.exec("cat \"#{file_md5}\"").split(' ')

      md5_local  = md5_local[0]  if md5_local.is_a?(Array)
      md5_remote = md5_remote[0] if md5_remote.is_a?(Array)
    rescue
      raise "unknown MD5 verification error: #{$!.to_s}"
    end

    raise IncorrectMD5, "MD5 signature is incorrect" if md5_remote != md5_local
    md5_remote
  end

  def iconv(path, from, to, inline = false)
    begin
      self.exec("iconv -c -f #{from} -t #{to} #{path} > #{path}.conv")
      File.rename("#{path}.conv", path) if inline
    rescue
      raise EncodingFailed, $!
    ensure
      File.unlink("#{path}.conv")  if File.exist?("#{path}.conv")
    end
  end

  def exec(cmd)
    begin
      rootdir = __FILE__.split('/')[0..-3].join('/')
      out_name = Digest::MD5.hexdigest(cmd + rand(Time.now().to_i).to_s) + '.log'

      while File.exist?("#{rootdir}/tmp/#{out_name}")
        STDERR.puts "=> File already exist: #{rootdir}/tmp/#{out_name}"
        out_name = Digest::MD5.hexdigest(cmd + rand(Time.now().to_i).to_s) + '.log'
        sleep 1
      end

      system("(#{cmd}) > #{rootdir}/tmp/#{out_name} 2>&1")
      out = File.open("#{rootdir}/tmp/#{out_name}", 'r').readlines.join
      raise StandardError, out.strip if $?.exitstatus > 0
      out
    rescue
      80.times { STDERR.print '-' }
      STDERR.puts
      STDERR.puts "=> System.exec: (#{cmd}) > #{rootdir}/tmp/#{out_name} 2>&1"
      STDERR.puts $!.backtrace
      STDERR.puts $!.message
      80.times { STDERR.print '-' }
      STDERR.puts
      raise $!
    ensure
      File.unlink("#{rootdir}/tmp/#{out_name}") if File.exist?("#{rootdir}/tmp/#{out_name}")
    end
  end
end

class Array
  def sort_array
    d = []
    self.each_with_index { |x, i| d[i] = [x, i]}
    if block_given?
      d.sort { |x, y| yield x[0], y[0] }.collect { |x| x[1] }
    else
      d.sort.collect { |x| x[1] }
    end
  end

  def sort_with(ord = [])
    return nil if self.length != ord.length
    self.values_at(*ord)
  end
end

class String
  def escape_path
    self.dump.gsub(/\s/, "\\ ")
  end

  # 'Natural order' comparison of two strings
  def String.natcmp(str1, str2, caseInsensitive=false)
    str1, str2 = str1.dup, str2.dup
    compareExpression = /^(\D*)(\d*)(.*)$/

    if caseInsensitive
      str1.downcase!
      str2.downcase!
    end

        # Remove all whitespace
    str1.gsub!(/\s*/, '')
    str2.gsub!(/\s*/, '')

    while (str1.length > 0) or (str2.length > 0) do
      # Extract non-digits, digits and rest of string
      str1 =~ compareExpression
      chars1, num1, str1 = $1.dup, $2.dup, $3.dup

      str2 =~ compareExpression
      chars2, num2, str2 = $1.dup, $2.dup, $3.dup

      # Compare the non-digits
      case (chars1 <=> chars2)
        when 0 # Non-digits are the same, compare the digits...
               # If either number begins with a zero, then compare
               # alphabetically, otherwise compare numerically
          if (num1[0] != 48) and (num2[0] != 48)
            num1, num2 = num1.to_i, num2.to_i
          end

          case (num1 <=> num2)
            when -1 then return -1
            when 1 then return 1
          end

        when -1 then return -1
        when 1 then return 1
      end # case

    end # while

    # Strings are naturally equal
    return 0
  end
end

class GPG

  class DecryptionFailed < StandardError
  end

  class EncryptionFailed < StandardError
  end

  def initialize
    @sys = System.new
    @rootdir = File.absolute_path("#{File.dirname(__FILE__)}/..")
  end

  def encrypt(file, recipients)
    begin
      @sys.exec("gpg --no-tty --homedir #{@rootdir}/etc/gnupg --batch --encrypt --sign -r #{recipients.join(' -r ')} < \"#{file}\" > \"#{file}.gpg\"")
      File.unlink(file)
    rescue
      File.delete("#{file}.gpg") if File.exist? "#{file}.gpg"
      raise $!
    end
  end

  def decrypt(file)
    if file =~ /^(.+)\.([gpg|pgp|asc]{3})$/
      begin
        @sys.exec("gpg --no-tty --homedir #{@rootdir}/etc/gnupg --batch -o \"#{$1}\" --decrypt \"#{$1}.#{$2}\" 2>&1")
        File.unlink(file)
      rescue
        File.unlink("#{$1}") if File.exist?("#{$1}")
        raise StandardError, 'failed to decrypt file' if out.split("\n").last =~ /gpg: decrypt_message failed: eof/
        raise $!
      end
    else
      begin
        @sys.exec("gpg --no-tty --homedir #{@rootdir}/etc/gnupg --batch -o \"#{file}.out\" --decrypt \"#{file}\"")
        File.rename("#{file}.out", file) if File.exist?("#{file}.out")
      rescue
        raise StandardError, "failed to decrypt file: '#{file}'" if $!.message =~ /gpg: decrypt_message failed: eof/
        raise $!
      ensure
        File.unlink("#{file}.out") if File.exist?("#{file}.out")
      end
    end
  end
end

class Logger
  def self.puts_error(id, log)
    File.open("log/transfer/#{id}.err", "a") { |f|
      f.write "================= trace - #{Time.now}  =================\n"
      f.write "#{log}\n"
      f.write "================= trace - #{Time.now}  =================\n\n"
      f.flush
    }
  end

  def self.puts(msg, type = :WARN)
    Syslog.open("FBService", Syslog::LOG_PID | Syslog::LOG_CONS, Syslog::LOG_LOCAL7) { |s| s.err msg.gsub(/\%/, "%%")     } if type == :ERROR
    Syslog.open("FBService", Syslog::LOG_PID | Syslog::LOG_CONS, Syslog::LOG_LOCAL7) { |s| s.notice msg.gsub(/\%/, "%%")  } if type == :NOTICE
    Syslog.open("FBService", Syslog::LOG_PID | Syslog::LOG_CONS, Syslog::LOG_LOCAL7) { |s| s.warning msg.gsub(/\%/, "%%") } if type == :WARN
  end
end

class Database
  class AccountNotExist < StandardError
  end

  class ClientNotExist < StandardError
  end

  class ClientAlreadyExist < StandardError
  end

  def initialize

    @cfg = {}
    File.open("#{File.dirname(__FILE__)}/../etc/filebroker.conf").readlines.each { |l|
      next if l !~ /^\S+\s*=\s*\S+$/
      @cfg["#{l.split('=').first}"] = l.split('=').last.strip
    }

    @db = PGconn.new(:hostaddr => @cfg['dbhost'], :port => @cfg['dbport'], :dbname => @cfg['dbname'], :user => @cfg['dbuser'], :password => @cfg['dbpass'])
    @sys = System.new
    @log2zbx = "/tech/nordea/common/bin/log2zbx.pl"
    @hostname = @sys.exec("hostname").strip
  end

  def initdb
    sql = File.open("#{File.dirname(__FILE__)}/../etc/fb_db.sql", "r").readlines
    @db.exec(sql)
  end

  def cleandb
    sql = "
    DELETE FROM fb_account
    "
  end

  def import_key(key)
    id = @db.exec("SELECT nextval('fb_keys_key_id_seq') AS id")[0]['id']
    File.open("#{File.dirname(__FILE__)}/../etc/keys/#{'0x%06x.key' % id}", 'w') { |fd| fd.write(File.open(key['file'], 'r').readline) }
    @db.exec("INSERT into fb_keys (key_id, type, description) VALUES (#{id}, E'#{key['type']}', E'#{key['description']}')")
    id
  end

  def insert_transfer(transfer_hash, source_id, target_id, source_path, target_path)
    id = @db.exec("SELECT nextval('fb_transfer_transfer_id_seq') AS id")[0]['id']
    @db.exec("INSERT into fb_transfer (transfer_id, transfer_hash, source_id, target_id, source_path, target_path) VALUES (#{id}, '#{transfer_hash}', #{source_id}, #{target_id}, '#{source_path}', '#{target_path}')")
    id
  end

  def insert_transfer_status(transfer_id, status_id, status_time)
    @db.exec("INSERT into fb_transfer_status (transfer_id, status_id, status_time) VALUES (#{transfer_id}, #{status_id}, '#{status_time}')")
    sql = "
    SELECT
      fb_transfer.transfer_id,
      fb_transfer.transfer_hash,
      fb_transfer_status.status_time,
      fb_transfer_status_dict.status_id,
      fb_transfer_status_dict.status_desc,
      fb_transfer.source_id,
      (SELECT fb_account.address FROM fb_account WHERE fb_account.account_id = fb_transfer.source_id) AS source_address,
      (SELECT fb_account.port FROM fb_account WHERE fb_account.account_id = fb_transfer.source_id) AS source_port,
      (SELECT fb_protocol_dict.protocol_desc FROM fb_protocol_dict, fb_account WHERE fb_protocol_dict.protocol_id = fb_account.protocol_id AND fb_account.account_id = fb_transfer.source_id) AS source_protocol,
      (SELECT fb_account.login FROM fb_account WHERE fb_account.account_id = fb_transfer.source_id) AS source_login,
      fb_transfer.source_path,
      fb_transfer.target_id,
      (SELECT fb_account.address FROM fb_account WHERE fb_account.account_id = fb_transfer.target_id) AS target_address,
      (SELECT fb_account.port FROM fb_account WHERE fb_account.account_id = fb_transfer.target_id) AS target_port,
      (SELECT fb_protocol_dict.protocol_desc FROM fb_protocol_dict, fb_account WHERE fb_protocol_dict.protocol_id = fb_account.protocol_id AND fb_account.account_id = fb_transfer.target_id) AS target_protocol,
      (SELECT fb_account.login FROM fb_account WHERE fb_account.account_id = fb_transfer.target_id) AS target_login,
      fb_transfer.target_path
    FROM
      public.fb_transfer,
      public.fb_transfer_status,
      public.fb_transfer_status_dict,
      public.fb_account
    WHERE
      fb_transfer_status.transfer_id = fb_transfer.transfer_id AND
      fb_transfer_status_dict.status_id = fb_transfer_status.status_id AND
      (fb_account.account_id = fb_transfer.source_id OR fb_account.account_id = fb_transfer.target_id) AND
      fb_transfer.transfer_id = #{transfer_id}
    ORDER BY fb_transfer_status.status_time ASC"

    t = @db.exec(sql)[0]
    if self.select_configuration('syslog') != 'false'
      log_str = "TID [#{t['transfer_hash']}]: #{t['status_desc'].upcase}: #{t['source_protocol']}://#{t['source_login']}@#{t['source_address']}:#{t['source_port']}:#{t['source_path']} => #{t['target_protocol']}://#{t['target_login']}@#{t['target_address']}:#{t['target_port']}:#{t['target_path']}"
      if status_id == FBService::TRANSFER_COMPLETED_SUCCESSFULLY
        Logger.puts(log_str, :NOTICE)
      elsif status_id == FBService::TRANSFER_COMPLETED_WITH_ERRORS
        Logger.puts(log_str, :ERROR)
      else
        Logger.puts(log_str, :WARN)
      end
    end
  end

  def update_transfer_status(transfer_id, status_id, status_time)
    @db.exec("UPDATE fb_transfer_status SET status_id=#{status_id}, status_time='#{status_time}' WHERE transfer_id=#{transfer_id}")
    sql = "
    SELECT
      fb_transfer.transfer_id,
      fb_transfer.transfer_hash,
      fb_transfer_status.status_time,
      fb_transfer_status_dict.status_id,
      fb_transfer_status_dict.status_desc,
      fb_transfer.source_id,
      (SELECT fb_account.address FROM fb_account WHERE fb_account.account_id = fb_transfer.source_id) AS source_address,
      (SELECT fb_account.port FROM fb_account WHERE fb_account.account_id = fb_transfer.source_id) AS source_port,
      (SELECT fb_protocol_dict.protocol_desc FROM fb_protocol_dict, fb_account WHERE fb_protocol_dict.protocol_id = fb_account.protocol_id AND fb_account.account_id = fb_transfer.source_id) AS source_protocol,
      (SELECT fb_account.login FROM fb_account WHERE fb_account.account_id = fb_transfer.source_id) AS source_login,
      fb_transfer.source_path,
      fb_transfer.target_id,
      (SELECT fb_account.address FROM fb_account WHERE fb_account.account_id = fb_transfer.target_id) AS target_address,
      (SELECT fb_account.port FROM fb_account WHERE fb_account.account_id = fb_transfer.target_id) AS target_port,
      (SELECT fb_protocol_dict.protocol_desc FROM fb_protocol_dict, fb_account WHERE fb_protocol_dict.protocol_id = fb_account.protocol_id AND fb_account.account_id = fb_transfer.target_id) AS target_protocol,
      (SELECT fb_account.login FROM fb_account WHERE fb_account.account_id = fb_transfer.target_id) AS target_login,
      fb_transfer.target_path
    FROM
      public.fb_transfer,
      public.fb_transfer_status,
      public.fb_transfer_status_dict,
      public.fb_account
    WHERE
      fb_transfer_status.transfer_id = fb_transfer.transfer_id AND
      fb_transfer_status_dict.status_id = fb_transfer_status.status_id AND
      (fb_account.account_id = fb_transfer.source_id OR fb_account.account_id = fb_transfer.target_id) AND
      fb_transfer.transfer_id = #{transfer_id}
    ORDER BY fb_transfer_status.status_time ASC"

    t = @db.exec(sql)[0]
    if self.select_configuration('syslog') != 'false'
      log_str = "TID [#{t['transfer_hash']}]: #{t['status_desc'].upcase}: #{t['source_protocol']}://#{t['source_login']}@#{t['source_address']}:#{t['source_port']}:#{t['source_path']} => #{t['target_protocol']}://#{t['target_login']}@#{t['target_address']}:#{t['target_port']}:#{t['target_path']}"
      if status_id.to_i == FBService::TRANSFER_COMPLETED_SUCCESSFULLY
        Logger.puts(log_str, :NOTICE)
      elsif status_id.to_i == FBService::TRANSFER_COMPLETED_WITH_ERRORS
        Logger.puts(log_str, :ERROR)
      else
        Logger.puts(log_str, :WARN)
      end
    end
  end

  def select_transfer_status(transfer_id)
    transfer_id = @db.exec("SELECT transfer_id FROM fb_transfer WHERE transfer_hash='#{transfer_id}'")[0]['transfer_id'] if transfer_id =~ /^\S{32}$/
    @db.exec("SELECT * FROM fb_transfer_status WHERE transfer_id=#{transfer_id}")[0]
  end

  def select_transfer_status_desc(status_id)
    @db.exec("SELECT status_desc FROM fb_transfer_status_dict WHERE status_id=#{status_id}")[0]['status_desc']
  end

  def insert_file_status(transfer_id, file, status_id, status_time)
    @db.exec("INSERT into fb_file_status (transfer_id, filename, status_id, status_time) VALUES (#{transfer_id}, '#{file}', #{status_id}, '#{status_time}')")
  end

  def update_file_status(transfer_id, file, status_id, status_time)
    if @db.exec("SELECT * FROM fb_file_status WHERE transfer_id=#{transfer_id} AND filename='#{file}'").count > 0
      @db.exec("UPDATE fb_file_status SET status_id=#{status_id}, status_time='#{status_time}' WHERE transfer_id=#{transfer_id} AND filename='#{file}'")
    else
      insert_file_status(transfer_id, file, status_id, status_time)
    end
  end

  def select_file_status(transfer_id)
    transfer_id = @db.exec("SELECT transfer_id FROM fb_transfer WHERE transfer_hash='#{transfer_id}'")[0]['transfer_id'] if transfer_id =~ /^\S{32}$/
    @db.exec("SELECT * FROM fb_file_status WHERE transfer_id=#{transfer_id} ORDER BY filename ASC")
  end

  def select_file_status_desc(status_id)
    @db.exec("SELECT status_desc FROM fb_file_status_dict WHERE status_id=#{status_id}")[0]['status_desc']
  end

  def select_file_status_type(status_id)
    @db.exec("SELECT status_type FROM fb_file_status_dict WHERE status_id=#{status_id}")[0]['status_type']
  end

  def select_configuration(key)
    @db.exec("SELECT value FROM fb_configuration WHERE key='#{key}'")[0]['value']
  end

  def insert_configuration(key, value)
    @db.exec("INSERT INTO fb_configuration (key, value) VALUES ('#{key}', '#{value}')")
  end

  def select_client_acl(client_id)
    @db.exec("SELECT ace_id FROM fb_client_acl WHERE client_id='#{client_id}'")
  end

  def select_client_acl_desc(ace_id)
    @db.exec("SELECT * FROM fb_client_acl_dict WHERE ace_id=#{ace_id}")[0]['ace_desc']
  end

  def select_transfer_by_status(status_id)
    @db.exec("SELECT * FROM fb_transfer WHERE status_id=#{status_id}")
  end

  def select_transfer_by_source(source_id)
    @db.exec("SELECT * FROM fb_transfer WHERE source_id=#{source_id}")
  end

  def select_running_transfers_by_source(status_id, source_id, source_path)
    @db.exec("SELECT fb_transfer.transfer_id FROM fb_transfer, fb_transfer_status WHERE fb_transfer.source_id=#{source_id} AND fb_transfer_status.status_id=#{status_id} AND fb_transfer.source_path='#{source_path}' AND fb_transfer.transfer_id=fb_transfer_status.transfer_id")
  end

  def select_running_transfers
    sql = "
    SELECT DISTINCT
      fb_transfer.transfer_id,
      fb_transfer.transfer_hash,
      fb_transfer_status.status_time,
      fb_transfer_status_dict.status_id,
      fb_transfer_status_dict.status_desc,
      fb_transfer.source_id,
      (SELECT fb_account.address FROM fb_account WHERE fb_account.account_id = fb_transfer.source_id) AS source_address,
      (SELECT fb_account.port FROM fb_account WHERE fb_account.account_id = fb_transfer.source_id) AS source_port,
      (SELECT fb_protocol_dict.protocol_desc FROM fb_protocol_dict, fb_account WHERE fb_protocol_dict.protocol_id = fb_account.protocol_id AND fb_account.account_id = fb_transfer.source_id) AS source_protocol,
      (SELECT fb_account.login FROM fb_account WHERE fb_account.account_id = fb_transfer.source_id) AS source_login,
      fb_transfer.source_path,
      fb_transfer.target_id,
      (SELECT fb_account.address FROM fb_account WHERE fb_account.account_id = fb_transfer.target_id) AS target_address,
      (SELECT fb_account.port FROM fb_account WHERE fb_account.account_id = fb_transfer.target_id) AS target_port,
      (SELECT fb_protocol_dict.protocol_desc FROM fb_protocol_dict, fb_account WHERE fb_protocol_dict.protocol_id = fb_account.protocol_id AND fb_account.account_id = fb_transfer.target_id) AS target_protocol,
      (SELECT fb_account.login FROM fb_account WHERE fb_account.account_id = fb_transfer.target_id) AS target_login,
      fb_transfer.target_path
    FROM
      public.fb_transfer,
      public.fb_transfer_status,
      public.fb_transfer_status_dict,
      public.fb_account
    WHERE
      fb_transfer_status.transfer_id = fb_transfer.transfer_id AND
      fb_transfer_status_dict.status_id = fb_transfer_status.status_id AND
      fb_transfer_status.status_id = #{FBService::TRANSFER_RUNNING} AND
      (fb_account.account_id = fb_transfer.source_id OR fb_account.account_id = fb_transfer.target_id)
    ORDER BY fb_transfer_status.status_time ASC"

    @db.exec(sql)
  end

  def select_last_transfers(n)
    sql = "
    SELECT DISTINCT
      fb_transfer.transfer_id,
      fb_transfer.transfer_hash,
      fb_transfer_status.status_time,
      fb_transfer_status_dict.status_id,
      fb_transfer_status_dict.status_desc,
      fb_transfer.source_id,
      (SELECT fb_account.address FROM fb_account WHERE fb_account.account_id = fb_transfer.source_id) AS source_address,
      (SELECT fb_account.port FROM fb_account WHERE fb_account.account_id = fb_transfer.source_id) AS source_port,
      (SELECT fb_protocol_dict.protocol_desc FROM fb_protocol_dict, fb_account WHERE fb_protocol_dict.protocol_id = fb_account.protocol_id AND fb_account.account_id = fb_transfer.source_id) AS source_protocol,
      (SELECT fb_account.login FROM fb_account WHERE fb_account.account_id = fb_transfer.source_id) AS source_login,
      fb_transfer.source_path,
      fb_transfer.target_id,
      (SELECT fb_account.address FROM fb_account WHERE fb_account.account_id = fb_transfer.target_id) AS target_address,
      (SELECT fb_account.port FROM fb_account WHERE fb_account.account_id = fb_transfer.target_id) AS target_port,
      (SELECT fb_protocol_dict.protocol_desc FROM fb_protocol_dict, fb_account WHERE fb_protocol_dict.protocol_id = fb_account.protocol_id AND fb_account.account_id = fb_transfer.target_id) AS target_protocol,
      (SELECT fb_account.login FROM fb_account WHERE fb_account.account_id = fb_transfer.target_id) AS target_login,
      fb_transfer.target_path
    FROM
      public.fb_transfer,
      public.fb_transfer_status,
      public.fb_transfer_status_dict,
      public.fb_account
    WHERE
      fb_transfer_status.transfer_id = fb_transfer.transfer_id AND
      fb_transfer_status_dict.status_id = fb_transfer_status.status_id AND
			fb_transfer_status.status_id <> #{FBService::TRANSFER_RUNNING} AND
      (fb_account.account_id = fb_transfer.source_id OR fb_account.account_id = fb_transfer.target_id)
    ORDER BY fb_transfer_status.status_time DESC LIMIT #{n}"

    @db.exec(sql)
  end

  def select_failed_transfers(n)
    sql = "
    SELECT
      fb_transfer.transfer_id,
      fb_transfer.transfer_hash,
      fb_transfer_status.status_time,
      fb_transfer_status_dict.status_id,
      fb_transfer_status_dict.status_desc,
      fb_transfer.source_id,
      (SELECT fb_account.address FROM fb_account WHERE fb_account.account_id = fb_transfer.source_id) AS source_address,
      (SELECT fb_account.port FROM fb_account WHERE fb_account.account_id = fb_transfer.source_id) AS source_port,
      (SELECT fb_protocol_dict.protocol_desc FROM fb_protocol_dict, fb_account WHERE fb_protocol_dict.protocol_id = fb_account.protocol_id AND fb_account.account_id = fb_transfer.source_id) AS source_protocol,
      (SELECT fb_account.login FROM fb_account WHERE fb_account.account_id = fb_transfer.source_id) AS source_login,
      fb_transfer.source_path,
      fb_transfer.target_id,
      (SELECT fb_account.address FROM fb_account WHERE fb_account.account_id = fb_transfer.target_id) AS target_address,
      (SELECT fb_account.port FROM fb_account WHERE fb_account.account_id = fb_transfer.target_id) AS target_port,
      (SELECT fb_protocol_dict.protocol_desc FROM fb_protocol_dict, fb_account WHERE fb_protocol_dict.protocol_id = fb_account.protocol_id AND fb_account.account_id = fb_transfer.target_id) AS target_protocol,
      (SELECT fb_account.login FROM fb_account WHERE fb_account.account_id = fb_transfer.target_id) AS target_login,
      fb_transfer.target_path
    FROM
      public.fb_transfer,
      public.fb_transfer_status,
      public.fb_transfer_status_dict,
      public.fb_account
    WHERE
      fb_transfer_status.transfer_id = fb_transfer.transfer_id AND
      fb_transfer_status_dict.status_id = fb_transfer_status.status_id AND
      fb_transfer_status.status_id = #{FBService::TRANSFER_COMPLETED_WITH_ERRORS} AND
      (fb_account.account_id = fb_transfer.source_id OR fb_account.account_id = fb_transfer.target_id)
    ORDER BY fb_transfer_status.status_time ASC LIMIT #{n}"

    @db.exec(sql)
  end

  def select_transfers_by_hash(h)
    sql = "
    SELECT
      fb_transfer.transfer_id,
      fb_transfer.transfer_hash,
      fb_transfer_status.status_time,
      fb_transfer_status_dict.status_id,
      fb_transfer_status_dict.status_desc,
      fb_transfer.source_id,
      (SELECT fb_account.address FROM fb_account WHERE fb_account.account_id = fb_transfer.source_id) AS source_address,
      (SELECT fb_account.port FROM fb_account WHERE fb_account.account_id = fb_transfer.source_id) AS source_port,
      (SELECT fb_protocol_dict.protocol_desc FROM fb_protocol_dict, fb_account WHERE fb_protocol_dict.protocol_id = fb_account.protocol_id AND fb_account.account_id = fb_transfer.source_id) AS source_protocol,
      (SELECT fb_account.login FROM fb_account WHERE fb_account.account_id = fb_transfer.source_id) AS source_login,
      fb_transfer.source_path,
      fb_transfer.target_id,
      (SELECT fb_account.address FROM fb_account WHERE fb_account.account_id = fb_transfer.target_id) AS target_address,
      (SELECT fb_account.port FROM fb_account WHERE fb_account.account_id = fb_transfer.target_id) AS target_port,
      (SELECT fb_protocol_dict.protocol_desc FROM fb_protocol_dict, fb_account WHERE fb_protocol_dict.protocol_id = fb_account.protocol_id AND fb_account.account_id = fb_transfer.target_id) AS target_protocol,
      (SELECT fb_account.login FROM fb_account WHERE fb_account.account_id = fb_transfer.target_id) AS target_login,
      fb_transfer.target_path
    FROM
      public.fb_transfer,
      public.fb_transfer_status,
      public.fb_transfer_status_dict,
      public.fb_account
    WHERE
      fb_transfer.transfer_hash = '#{h}' AND
      fb_transfer_status.transfer_id = fb_transfer.transfer_id AND
      fb_transfer_status_dict.status_id = fb_transfer_status.status_id AND
      (fb_account.account_id = fb_transfer.source_id OR fb_account.account_id = fb_transfer.target_id)
    ORDER BY fb_transfer_status.status_time ASC"

    @db.exec(sql)
  end

  def select_transfer_between(s_time, e_time)
    sql = "
      SELECT
        fb_transfer.transfer_id,
        fb_transfer.transfer_hash,
        fb_transfer_status.status_time,
        fb_transfer_status_dict.status_id,
        fb_transfer_status_dict.status_desc,
        fb_transfer.source_id,
        (SELECT fb_account.address FROM fb_account WHERE fb_account.account_id = fb_transfer.source_id) AS source_address,
        (SELECT fb_account.port FROM fb_account WHERE fb_account.account_id = fb_transfer.source_id) AS source_port,
        (SELECT fb_protocol_dict.protocol_desc FROM fb_protocol_dict, fb_account WHERE fb_protocol_dict.protocol_id = fb_account.protocol_id AND fb_account.account_id = fb_transfer.source_id) AS source_protocol,
        (SELECT fb_account.login FROM fb_account WHERE fb_account.account_id = fb_transfer.source_id) AS source_login,
        fb_transfer.source_path,
        fb_transfer.target_id,
        (SELECT fb_account.address FROM fb_account WHERE fb_account.account_id = fb_transfer.target_id) AS target_address,
        (SELECT fb_account.port FROM fb_account WHERE fb_account.account_id = fb_transfer.target_id) AS target_port,
        (SELECT fb_protocol_dict.protocol_desc FROM fb_protocol_dict, fb_account WHERE fb_protocol_dict.protocol_id = fb_account.protocol_id AND fb_account.account_id = fb_transfer.target_id) AS target_protocol,
        (SELECT fb_account.login FROM fb_account WHERE fb_account.account_id = fb_transfer.target_id) AS target_login,
        fb_transfer.target_path
      FROM
        public.fb_transfer,
        public.fb_transfer_status,
        public.fb_transfer_status_dict,
        public.fb_account
      WHERE
        fb_transfer.source_id = fb_account.account_id AND
        fb_transfer.target_id = fb_account.account_id AND
        fb_transfer_status.transfer_id = fb_transfer.transfer_id AND
        fb_transfer_status_dict.status_id = fb_transfer_status.status_id AND
        fb_transfer_status.status_time > '#{s_time}' AND
        fb_transfer_status.status_time < '#{e_time}'
      ORDER BY fb_transfer_status.status_time ASC"
    @db.exec(sql)
  end

  def select_transfer_files(transfer_id)
    sql = "
    SELECT
      fb_file_status.transfer_id,
      fb_file_status.filename,
      fb_file_status.status_time,
      fb_file_status_dict.status_id,
      fb_file_status_dict.status_desc,
      fb_file_status_dict.status_type
    FROM
      public.fb_file_status,
      public.fb_file_status_dict
    WHERE
      fb_file_status.status_id = fb_file_status_dict.status_id AND
      fb_file_status.transfer_id = #{transfer_id}
    ORDER BY fb_file_status.filename ASC"
    @db.exec(sql)
  end

  def add_client(client)
    if @db.exec("SELECT login FROM fb_client WHERE login=E'#{client['login']}'")[0] != nil
      raise ClientAlreadyExist, "login already exist"
    end

    id = @db.exec('SELECT nextval(fb_client_client_id_seq)')[0][0]
    @db.exec("INSERT INTO fb_client (client_id, login, password) VALUES (#{id}, '#{client['login']}', '#{client['password']}')")

    client['acl'].each { |x| @db.exec("INSERT INTO fb_client_acl (client_id, ace_id) VALUES (#{id}, #{self.get_client_acl_id(x)})") }
    client['client_id'] = id
    client
  end

  def remove_client(client)
    client['client_id'] = @db.exec("SELECT client_id FROM fb_client WHERE login=E'#{client['login']}'")[0]['client_id']
    @db.exec("DELETE FROM fb_client_acl WHERE client_id='#{client['client_id']}'")
    @db.exec("DELETE FROM fb_client WHERE login=E'#{client['login']}'")
  end

  def get_client(client)
    if client['id'] != nil
      @db.exec("SELECT * FROM fb_client WHERE client_id=#{client['client_id']}").each { |x|
        client['login'] 	= x['login']
        client['password'] = x['password']
        return client
      }
    elsif client['login'] != nil
      @db.exec("SELECT * FROM fb_client WHERE login=E'#{client['login']}'").each { |x|
        client['client_id'] = x['client_id']
        client['password']  = x['password']
        return client
      }
    end

    raise ClientNotExist, "client does not exist"
  end

  def get_client_list
    @db.exec('SELECT * FROM fb_client')
  end

  def get_client_acl(client)
    client['acl'] = []
    sql = "
      SELECT
        fb_client_acl.ace_id,
        fb_client_acl_dict.ace_desc,
        fb_client_acl.client_id
      FROM
        public.fb_client_acl_dict,
        public.fb_client_acl
      WHERE
        fb_client_acl.ace_id = fb_client_acl_dict.ace_id AND
        fb_client_acl.client_id = #{client['client_id']}"
    @db.exec(sql).each { |x| client['acl'] << x['ace_desc'] }
    return client
    raise ClientNotExist, 'client does not exist'
  end

  def insert_client(client)
    client['client_id'] = @db.exec("INSERT INTO fb_client (login, password) VALUES ('#{client['login']}', '#{client['password']}') RETURNING client_id")[0]['client_id']
    client['acl'].each { |x|
      @db.exec("INSERT INTO fb_client_acl (client_id, ace_id) VALUES (#{client['client_id']}, #{self.get_client_acl_id(x)})")
    }
  end

  def get_client_acl_id(desc)
    @db.exec("SELECT * FROM fb_client_acl_dict WHERE ace_desc='#{desc}'")[0]['ace_id']
  end

  def set_client_acl(client)
    @db.exec("DELETE FROM fb_client_acl WHERE client_id=#{client['client_id']}")
    client['acl'].each { |x|
      @db.exec("INSERT INTO fb_client_acl (client_id, ace_id) VALUES (#{client['client_id']}, #{self.get_client_acl_id(x)})")
    }
  end

  def set_client_password(client)
    @db.exec("UPDATE fb_client SET password='#{client['password']}' WHERE login=E'#{client['login']}'");
  end

  def insert_account(account)
    id = @db.exec("SELECT nextval('fb_account_account_id_seq') AS id")[0]['id']
    @db.exec("INSERT INTO fb_account (account_id, protocol_id, address, port, login) VALUES (#{id}, (SELECT protocol_id FROM fb_protocol_dict WHERE protocol_desc='#{account['protocol']}'), '#{account['address']}', #{account['port']}, E'#{account['login'].gsub(/\\/, '\\\\\\\\')}')")

    account['account_id'] = id
    account
  end

  def remove_account(account)

  end

  def select_account(account)
    if account['account_id'] != nil
      @db.exec("SELECT * FROM fb_account WHERE account_id=#{account['account_id']}").each { |x|
        account['protocol_id'] 	= x['protocol_id']
        account['address'] 		  = x['address']
        account['port'] 		    = x['port']
        account['login'] 		    = x['login']
        return account
      }
    else
      @db.exec("SELECT * FROM fb_account WHERE protocol_id=(SELECT protocol_id FROM fb_protocol_dict WHERE protocol_desc='#{account['protocol']}') AND address='#{account['address']}' AND port=#{account['port']} AND login=E'#{account['login'].gsub(/\\/, '\\\\\\\\')}'").each { |x|
        account['account_id'] = x['account_id']
        return account
      }
    end

    raise AccountNotExist, 'account does not exist'
  end

  def select_configuration(key)
    return @db.exec('SELECT * FROM fb_configuration') if key == 'all'
    @db.exec("SELECT value FROM fb_configuration WHERE key='#{key}'").each { |x| return x['value'] }
  end

  def update_configuration(key, value)
    @db.exec("UPDATE fb_configuration SET VALUE='#{value}' WHERE key='#{key}'")
  end

  def select_key_list
    @db.exec('SELECT * FROM fb_keys')
  end

end

class Connector
  class AuthenticationFailed < StandardError
  end

  class HostUnreachable < StandardError
  end

  class NetworkUnreachable < StandardError
  end

  class BadNetworkName < StandardError
  end

  class ConnectionRefused < StandardError
  end

  class ConnectionBroken < StandardError
  end

  class ConnectionRefused < StandardError
  end

  class NoSuchFileOrDirectory < StandardError
  end

  class ErrorOpeningLocalFile < StandardError
  end

  class PermissionDenied < StandardError
  end

  class FileAlreadyExist < StandardError
  end

  class NoSpaceLeft < StandardError
  end

  class SFTP
    attr_accessor :address, :port, :login, :password

    def connect
      begin
        if @password != ''
          @sftp = Net::SFTP.start(@address, @login, :password => @password, :port => @port)
        else
          @sftp = Net::SFTP.start(@address, @login, :port => @port, :keys => [ "#{File.dirname(__FILE__)}/../etc/ssh/id_rsa" ])
        end
      rescue Net::SSH::AuthenticationFailed
        raise Connector::AuthenticationFailed, 'login credentials rejected'
      rescue Net::SFTP::StatusException => e
        raise Connector::PermissionDenied if e.description == 'permission denied'
        raise e
      rescue
        msg = $!.to_s
        raise Connector::HostUnreachable, "unable to resolve host: '#{@address}'" if $!.to_s =~ /getaddrinfo: Name or service not known/
        raise "cannot connect to '#{@address}': #{msg}"
      end
    end

    def disconnect
      begin
        @sftp.session.close
      rescue
        msg = $!.to_s
        raise "error on disconnect: #{msg}"
      end
    end

    def put(src, dst)
      begin
        @sftp.upload!(src, dst)
      rescue
        msg = $!.to_s
        raise "cannot upload file '#{src.split("/").last}': #{msg}"
      end
    end

    def get(src, dst)
      begin
        @sftp.download!(src, dst)
      rescue
        msg = $!.to_s
        raise Connector::NoSuchFileOrDirectory, "no such file or directory: '#{src}'" if $!.to_s =~ /no such file/
        raise Connector::PermissionDenied, "permission denied: '#{src}'" if $!.to_s =~ /permission denied/
        raise "cannot download file '#{src.split("/").last}': #{msg}"
      end
    end

    def remove(file)
      begin
        @sftp.remove!(file)
      rescue Net::SFTP::StatusException => e
        raise Connector::PermissionDenied, "permission denied: '#{file}'" if e.description == 'permission denied'
        raise e
      rescue
        msg = $!.to_s
        raise Connector::NoSuchFileOrDirectory, "no such file or directory: '#{file}'" if $!.to_s =~ /no such file/
        raise "cannot remove file '#{file.split("/").last}': #{msg}"
      end
    end

    def rename(src, dst)
      begin
        #begin
        #  @sftp.remove!(dst) if sftp.stat!(dst) != nil
        #rescue
        #end
        @sftp.rename!(src, dst, 0x0004)
      rescue Net::SFTP::StatusException => e
        raise Connector::PermissionDenied, "permission denied: '#{dst}'" if e.description == 'permission denied'
        raise Connector::FileAlreadyExist, "file already exist: '#{dst}'" if e.description == 'failure'
        raise e
      rescue
        msg = $!.to_s
        raise Connector::NoSuchFileOrDirectory, "no such file or directory: '#{src}'" if $!.to_s =~ /no such file/
        raise "cannot rename file '#{src.split("/").last}': #{msg}"
      end
    end

    def list(path)
      begin
        items = []
        fd = @sftp.opendir!(path)
        while (entries = @sftp.readdir!(fd)) do
          entries.each { |item|
            next if item.name == "." or item.name == ".."
            next if item.longname[0] != '-'

            i = {}
            i['name']  = item.name
            i['size']  = item.attributes.size
            i['mtime'] = i['mtime'] = Time.at(item.attributes.mtime).to_s
            items << i
          }
        end

        return items.sort { |a, b| a['name'] <=> b['name'] }
      rescue Net::SFTP::StatusException => e
        raise Connector::PermissionDenied, "permission denied: '#{path}'" if e.description == 'permission denied'
        raise e
      rescue
        msg = $!.to_s
        raise Connector::NoSuchFileOrDirectory, "no such file or directory: #{path}" if $!.to_s =~ /no such file/
      end
    end
  end

  class FTP
    attr_accessor :address, :port, :login, :password, :presite, :postsite, :passive, :text, :binary

    def connect
      begin
        @ftp = Net::FTP.new
        @ftp.debug_mode = false
        @ftp.connect(@address, @port)
        @ftp.login(@login, @password)
        @ftp.passive = true if @passive == 'true'
      rescue
        raise Connector::HostUnreachable, "unable to resolve: '#{@address}'" if $!.to_s =~ /getaddrinfo: Name or service not known/
        raise Connector::ConnectionRefused, "connection refused: '#{@address}'" if $!.to_s =~ /onnection refused/
      end
    end

    def disconnect
      begin
        @ftp.quit
        @ftp.close
      rescue
        msg = $!.to_s
        raise "error on disconnect: #{msg}"
      end
    end

    def put(src, dst)
      begin
        @ftp.site(@presite)			      if @presite   != ''
        if @binary == "true" or @text == "" or @text == "false"
          @ftp.putbinaryfile(src, dst)
        else
          @ftp.puttextfile(src, dst)
        end
        @ftp.site(@postsite)			    if @postsite  != ""
      rescue
        msg = $!.to_s
        raise "cannot upload file '#{src.split("/").last}': #{msg}"
      end
    end

    def get(src, dst)
      begin
        @ftp.site(@presite)			      if @presite != ''

        if @binary == 'true' or @text == '' or @text == 'false'
          @ftp.getbinaryfile(src, dst)
        else
          @ftp.gettextfile(src, dst)
        end
        @ftp.site(@postsite)			    if @postsite != ''
      rescue
        msg = $!.to_s
        raise Connector::NoSuchFileOrDirectory, "no such file or directory: '#{src}'" if $!.to_s =~ /no such file/
        raise Connector::PermissionDenied, "permission denied: '#{src}'" if $!.to_s =~ /permission denied/
        raise "cannot download file '#{src.split("/").last}': #{msg.strip}"
      end
    end

    def remove(file)
      begin
        @ftp.delete(file)
      rescue
        msg = $!.to_s
        raise Connector::NoSuchFileOrDirectory, "no such file or directory: #{file}" if $!.to_s =~ /no such file/
        raise "cannot remove file '#{file.split("/").last}': #{msg.strip}"
      end
    end

    def rename(src, dst)
      begin
        @ftp.rename(src, dst)
      rescue
        msg = $!.to_s
        raise Connector::NoSuchFileOrDirectory, "no such file or directory: #{src}" if $!.to_s =~ /no such file/
        raise "cannot rename file '#{src.split("/").last}': #{msg}"
      end
    end

    def list(path)
      begin
        items = []
        @ftp.chdir(path)
        @ftp.list.each { |e|
          i = {}
          item = Net::FTP::List.parse(e)
          next unless item.file?
          i['name']  = item.name
          i['size']  = item.size
          i['mtime'] = Time.at(Time.parse(item.mtime.to_s).to_i).to_s
          items << i
        }

        return items.sort { |a, b| a['name'] <=> b['name'] }
      rescue
        msg = $!.to_s
        raise Connector::NoSuchFileOrDirectory, "no such file: #{path}" if $!.to_s =~ /no such file/
        raise "cannot get file list: #{msg}"
      end
    end
  end

  class CIFS
    attr_accessor :address, :port, :login, :password, :share, :debug

    def initialize
      @debug = false
      @sys = System.new
      @port = 445
    end

    def connect
      begin
        @rand  = Digest::MD5.hexdigest("#{@address}_#{@port}_#{@share}_#{@login}" + rand(Time.now().to_i).to_s)
        @authfile = "tmp/#{@rand}.pwd"
      rescue
        raise $!
      end
    end

    def disconnect
      File.delete(@authfile) if File.exist?(@authfile)
    end

    def cd(path)
      begin
        File.open(@authfile, 'w', 0600) { |fd| fd.puts "username = #{@login}\npassword = #{@password}\n" }

        cmd = ''
        path.split('/').each { |item|
          next if item == ''
          next if item == path.split('/')[1] # share
          cmd = cmd + "cd \\\"#{item}\\\"\n"
        }

        @sys.exec("echo \"#{cmd}\" | smbclient -E -g -A #{@authfile} -p #{@port} //#{@address}/#{@share} 2>&1").split("\n").each { |out|
          raise Connector::AuthenticationFailed, "login refused by '#{@address}'"            if out =~ /NT_STATUS_LOGON_FAILURE/
          raise Connector::AuthenticationFailed, "login refused by '#{@address}'"            if out =~ /NT_STATUS_ACCESS_DENIED/
          raise Connector::HostUnreachable, "host unreachable '#{@address}'"                 if out =~ /NT_STATUS_UNSUCCESSFUL/
          raise Connector::HostUnreachable, "host unreachable '#{@address}'"                 if out =~ /NT_STATUS_HOST_UNREACHABLE/
          raise Connector::BadNetworkName, "bad network name '#{@address}'"                  if out =~ /NT_STATUS_BAD_NETWORK_NAME/
          raise Connector::NetworkUnreachable, "network unreachable '#{@address}'"           if out =~ /NT_STATUS_NETWORK_UNREACHABLE/
          raise Connector::ConnectionRefused, "connection refused by '#{@address}'"          if out =~ /NT_STATUS_CONNECTION_REFUSED/
          raise Connector::ConnectionRefused, "connection refused by '#{@address}'"          if out =~ /Receiving SMB: Server \S+ stopped responding/
          raise Connector::NoSuchFileOrDirectory, "cannot open file or directory '#{path}'"  if out =~ /NT_STATUS_OBJECT_(NAME|PATH)_NOT_FOUND/
          raise Connector::PermissionDenied, "permission denied for file '#{path}'"          if out =~ /NT_STATUS_ACCOUNT_DISABLED/
          raise Connector::PermissionDenied, "permission denied for file '#{path}'"          if out =~ /NT_STATUS_ACCOUNT_LOCKED/
          raise Connector::PermissionDenied, "permission denied for file '#{path}'"          if out =~ /not a directory/
        }
      rescue
        raise Connector::AuthenticationFailed, "login refused by '#{@address}'"            if $!.message =~ /NT_STATUS_LOGON_FAILURE/
        raise Connector::AuthenticationFailed, "login refused by '#{@address}'"            if $!.message =~ /NT_STATUS_ACCESS_DENIED/
        raise Connector::HostUnreachable, "host unreachable '#{@address}'"                 if $!.message =~ /NT_STATUS_UNSUCCESSFUL/
        raise Connector::HostUnreachable, "host unreachable '#{@address}'"                 if $!.message =~ /NT_STATUS_HOST_UNREACHABLE/
        raise Connector::BadNetworkName, "bad network name '#{@address}'"                  if $!.message =~ /NT_STATUS_BAD_NETWORK_NAME/
        raise Connector::NetworkUnreachable, "network unreachable '#{@address}'"           if $!.message =~ /NT_STATUS_NETWORK_UNREACHABLE/
        raise Connector::ConnectionRefused, "connection refused by '#{@address}'"          if $!.message =~ /NT_STATUS_CONNECTION_REFUSED/
        raise Connector::ConnectionRefused, "connection refused by '#{@address}'"          if $!.message =~ /Receiving SMB: Server \S+ stopped responding/
        raise Connector::NoSuchFileOrDirectory, "cannot open file or directory '#{path}'"  if $!.message =~ /NT_STATUS_OBJECT_(NAME|PATH)_NOT_FOUND/
        raise Connector::PermissionDenied, "permission denied for file '#{path}'"          if $!.message =~ /NT_STATUS_ACCOUNT_DISABLED/
        raise Connector::PermissionDenied, "permission denied for file '#{path}'"          if $!.message =~ /NT_STATUS_ACCOUNT_LOCKED/
        raise Connector::PermissionDenied, "permission denied for file '#{path}'"          if $!.message =~ /not a directory/
        raise $!
      ensure
        File.delete(@authfile) if File.exist?(@authfile)
      end
    end

    def put(src, dst)
      begin
        File.open(@authfile, 'w', 0600) { |fd| fd.puts "username = #{@login}\npassword = #{@password}\n" }
        STDERR.puts "->putting #{src}" if @debug

        cmd = ''
        dst.split('/')[0...-1].each { |item|
          next if item == ''
          next if item == dst.split('/')[1] # share
          cmd = cmd + "cd \\\"#{item}\\\"\n"
        }

        cmd = cmd + "put \\\"#{src}\\\" \\\"#{dst.split('/').last}\\\"\n"
        @sys.exec("echo \"#{cmd}\" | smbclient -E -g -A #{@authfile} -p #{@port} //#{@address}/#{@share} 2>&1").split("\n").each { |out|
          raise Connector::AuthenticationFailed, "login refused by '#{@address}'"           if out =~ /NT_STATUS_LOGON_FAILURE/
          raise Connector::AuthenticationFailed, "login refused by '#{@address}'"           if out =~ /NT_STATUS_ACCESS_DENIED/
          raise Connector::HostUnreachable, "host unreachable '#{@address}'"                if out =~ /NT_STATUS_UNSUCCESSFUL/
          raise Connector::HostUnreachable, "host unreachable '#{@address}'"                if out =~ /NT_STATUS_HOST_UNREACHABLE/
          raise Connector::BadNetworkName, "bad network name '#{@address}'"                 if out =~ /NT_STATUS_BAD_NETWORK_NAME/
          raise Connector::NetworkUnreachable, "network unreachable '#{@address}'"          if out =~ /NT_STATUS_NETWORK_UNREACHABLE/
          raise Connector::ConnectionRefused, "connection refused by '#{@address}'"         if out =~ /NT_STATUS_CONNECTION_REFUSED/
          raise Connector::ConnectionRefused, "connection refused by '#{@address}'"         if out =~ /Receiving SMB: Server \S+ stopped responding/
          raise Connector::NoSuchFileOrDirectory, "cannot open file or directory '#{dst}'"  if out =~ /NT_STATUS_OBJECT_(NAME|PATH)_NOT_FOUND/
          raise Connector::PermissionDenied, "permission denied for file '#{dst}'"          if out =~ /NT_STATUS_ACCESS_DENIED/
          raise Connector::NoSpaceLeft, "no space left for file '#{dst}'"                   if out =~ /NT_STATUS_DISK_FULL/
          raise Connector::PermissionDenied, "permission denied for file '#{path}'"         if out =~ /NT_STATUS_ACCOUNT_DISABLED/
          raise Connector::PermissionDenied, "permission denied for file '#{path}'"         if out =~ /NT_STATUS_ACCOUNT_LOCKED/
          raise Connector::PermissionDenied, "unknown error '#{out}'"                       if out =~ /session setup failed/
          raise Connector::PermissionDenied, "permission denied for file '#{path}'"         if out =~ /not a directory/
        }
      rescue
        raise Connector::AuthenticationFailed, "login refused by '#{@address}'"           if $!.message =~ /NT_STATUS_LOGON_FAILURE/
        raise Connector::AuthenticationFailed, "login refused by '#{@address}'"           if $!.message =~ /NT_STATUS_ACCESS_DENIED/
        raise Connector::HostUnreachable, "host unreachable '#{@address}'"                if $!.message =~ /NT_STATUS_UNSUCCESSFUL/
        raise Connector::HostUnreachable, "host unreachable '#{@address}'"                if $!.message =~ /NT_STATUS_HOST_UNREACHABLE/
        raise Connector::BadNetworkName, "bad network name '#{@address}'"                 if $!.message =~ /NT_STATUS_BAD_NETWORK_NAME/
        raise Connector::NetworkUnreachable, "network unreachable '#{@address}'"          if $!.message =~ /NT_STATUS_NETWORK_UNREACHABLE/
        raise Connector::ConnectionRefused, "connection refused by '#{@address}'"         if $!.message =~ /NT_STATUS_CONNECTION_REFUSED/
        raise Connector::ConnectionRefused, "connection refused by '#{@address}'"         if $!.message =~ /Receiving SMB: Server \S+ stopped responding/
        raise Connector::NoSuchFileOrDirectory, "cannot open file or directory '#{dst}'"  if $!.message =~ /NT_STATUS_OBJECT_(NAME|PATH)_NOT_FOUND/
        raise Connector::PermissionDenied, "permission denied for file '#{dst}'"          if $!.message =~ /NT_STATUS_ACCESS_DENIED/
        raise Connector::NoSpaceLeft, "no space left for file '#{dst}'"                   if $!.message =~ /NT_STATUS_DISK_FULL/
        raise Connector::PermissionDenied, "permission denied for file '#{path}'"         if $!.message =~ /NT_STATUS_ACCOUNT_DISABLED/
        raise Connector::PermissionDenied, "permission denied for file '#{path}'"         if $!.message =~ /NT_STATUS_ACCOUNT_LOCKED/
        raise Connector::PermissionDenied, "permission denied for file '#{path}'"         if $!.message =~ /not a directory/
        raise $!
      ensure
        File.delete(@authfile) if File.exist?(@authfile)
      end
    end

    def get(src, dst)
      begin
        File.open(@authfile, 'w', 0600) { |fd| fd.puts "username = #{@login}\npassword = #{@password}\n" }
        STDERR.puts "->getting #{src}" if @debug

        cmd = ''
        src.split('/')[0...-1].each { |item|
          next if item == ''
          next if item == src.split('/')[1] # share
          cmd = cmd + "cd \\\"#{item}\\\"\n"
        }

        cmd = cmd + "get \\\"#{src.split('/').last}\\\" \\\"#{dst}\\\"\n"
        @sys.exec("echo \"#{cmd}\" | smbclient -E -g -A #{@authfile} -p #{@port} //#{@address}/#{@share} 2>&1").split("\n").each { |out|
          raise Connector::AuthenticationFailed, "login refused by '#{@address}'"           if out =~ /NT_STATUS_LOGON_FAILURE/
          raise Connector::AuthenticationFailed, "login refused by '#{@address}'"           if out =~ /NT_STATUS_ACCESS_DENIED/
          raise Connector::HostUnreachable, "host unreachable '#{@address}'"                if out =~ /NT_STATUS_UNSUCCESSFUL/
          raise Connector::HostUnreachable, "host unreachable '#{@address}'"                if out =~ /NT_STATUS_HOST_UNREACHABLE/
          raise Connector::BadNetworkName, "bad network name '#{@address}'"                 if out =~ /NT_STATUS_BAD_NETWORK_NAME/
          raise Connector::NetworkUnreachable, "network unreachable '#{@address}'"          if out =~ /NT_STATUS_NETWORK_UNREACHABLE/
          raise Connector::ConnectionRefused, "connection refused by '#{@address}'"         if out =~ /NT_STATUS_CONNECTION_REFUSED/
          raise Connector::ConnectionRefused, "connection refused by '#{@address}'"         if out =~ /Receiving SMB: Server \S+ stopped responding/
          raise Connector::NoSuchFileOrDirectory, "cannot open file or directory '#{src}'"  if out =~ /NT_STATUS_OBJECT_(NAME|PATH)_NOT_FOUND/
          raise Connector::PermissionDenied, "permission denied for file '#{src}'"          if out =~ /NT_STATUS_ACCESS_DENIED/
          raise Connector::NoSpaceLeft, "no space left for file '#{src}'"                   if out =~ /NT_STATUS_DISK_FULL/
          raise Connector::PermissionDenied, "permission denied for file '#{path}'"         if out =~ /NT_STATUS_ACCOUNT_DISABLED/
          raise Connector::PermissionDenied, "permission denied for file '#{path}'"         if out =~ /NT_STATUS_ACCOUNT_LOCKED/
          raise Connector::PermissionDenied, "permission denied for file '#{path}'"         if out =~ /NT_STATUS_FILE_IS_A_DIRECTORY opening remote file/
          raise Connector::PermissionDenied, "unknown error '#{out}'"                       if out =~ /session setup failed/
          raise Connector::PermissionDenied, "permission denied for file '#{path}'"         if out =~ /not a directory/
        }
      rescue
        raise Connector::AuthenticationFailed, "login refused by '#{@address}'"           if $!.message =~ /NT_STATUS_LOGON_FAILURE/
        raise Connector::AuthenticationFailed, "login refused by '#{@address}'"           if $!.message =~ /NT_STATUS_ACCESS_DENIED/
        raise Connector::HostUnreachable, "host unreachable '#{@address}'"                if $!.message =~ /NT_STATUS_UNSUCCESSFUL/
        raise Connector::HostUnreachable, "host unreachable '#{@address}'"                if $!.message =~ /NT_STATUS_HOST_UNREACHABLE/
        raise Connector::BadNetworkName, "bad network name '#{@address}'"                 if $!.message =~ /NT_STATUS_BAD_NETWORK_NAME/
        raise Connector::NetworkUnreachable, "network unreachable '#{@address}'"          if $!.message =~ /NT_STATUS_NETWORK_UNREACHABLE/
        raise Connector::ConnectionRefused, "connection refused by '#{@address}'"         if $!.message =~ /NT_STATUS_CONNECTION_REFUSED/
        raise Connector::ConnectionRefused, "connection refused by '#{@address}'"         if $!.message =~ /Receiving SMB: Server \S+ stopped responding/
        raise Connector::NoSuchFileOrDirectory, "cannot open file or directory '#{src}'"  if $!.message =~ /NT_STATUS_OBJECT_(NAME|PATH)_NOT_FOUND/
        raise Connector::PermissionDenied, "permission denied for file '#{src}'"          if $!.message =~ /NT_STATUS_ACCESS_DENIED/
        raise Connector::NoSpaceLeft, "no space left for file '#{src}'"                   if $!.message =~ /NT_STATUS_DISK_FULL/
        raise Connector::PermissionDenied, "permission denied for file '#{path}'"         if $!.message =~ /NT_STATUS_ACCOUNT_DISABLED/
        raise Connector::PermissionDenied, "permission denied for file '#{path}'"         if $!.message =~ /NT_STATUS_ACCOUNT_LOCKED/
        raise Connector::PermissionDenied, "permission denied for file '#{path}'"         if $!.message =~ /not a directory/
        raise $!
      ensure
        File.delete(@authfile) if File.exist?(@authfile)
      end
    end

    def remove(file)
      begin
        File.open(@authfile, 'w', 0600) { |fd| fd.puts "username = #{@login}\npassword = #{@password}\n" }
        STDERR.puts "->removing #{file}" if @debug

        cmd = ''
        file.split('/')[0...-1].each { |item|
          next if item == ''
          next if item == file.split('/')[1] # share
          cmd = cmd + "cd \\\"#{item}\\\"\n"
        }

        cmd = cmd + "rm \\\"#{file.split('/').last}\\\"\n"
        @sys.exec("echo \"#{cmd}\" | smbclient -E -g -A #{@authfile} -p #{@port} //#{@address}/#{@share} 2>&1").split("\n").each { |out|
          raise Connector::AuthenticationFailed, "login refused by '#{@address}'"            if out =~ /NT_STATUS_LOGON_FAILURE/
          raise Connector::AuthenticationFailed, "login refused by '#{@address}'"            if out =~ /NT_STATUS_ACCESS_DENIED/
          raise Connector::HostUnreachable, "host unreachable '#{@address}'"                 if out =~ /NT_STATUS_UNSUCCESSFUL/
          raise Connector::HostUnreachable, "host unreachable '#{@address}'"                 if out =~ /NT_STATUS_HOST_UNREACHABLE/
          raise Connector::BadNetworkName, "bad network name '#{@address}'"                  if out =~ /NT_STATUS_BAD_NETWORK_NAME/
          raise Connector::NetworkUnreachable, "network unreachable '#{@address}'"           if out =~ /NT_STATUS_NETWORK_UNREACHABLE/
          raise Connector::ConnectionRefused, "connection refused by '#{@address}'"          if out =~ /NT_STATUS_CONNECTION_REFUSED/
          raise Connector::ConnectionRefused, "connection refused by '#{@address}'"          if out =~ /Receiving SMB: Server \S+ stopped responding/
          raise Connector::NoSuchFileOrDirectory, "cannot open file or directory '#{file}'"  if out =~ /NT_STATUS_OBJECT_(NAME|PATH)_NOT_FOUND/
          raise Connector::NoSuchFileOrDirectory, "cannot open file or directory '#{file}'"  if out =~ /NT_STATUS_NO_SUCH_FILE/
          raise Connector::PermissionDenied, "permission denied for file '#{file}'"          if out =~ /NT_STATUS_ACCESS_DENIED/
          raise Connector::PermissionDenied, "permission denied for file '#{file}'"          if out =~ /NT_STATUS_CANNOT_DELETE/
          raise Connector::PermissionDenied, "permission denied for file '#{path}'"          if out =~ /NT_STATUS_ACCOUNT_DISABLED/
          raise Connector::PermissionDenied, "permission denied for file '#{path}'"          if out =~ /NT_STATUS_ACCOUNT_LOCKED/
          raise Connector::PermissionDenied, "unknown error '#{out}'"                        if out =~ /session setup failed/
          raise Connector::PermissionDenied, "permission denied for file '#{path}'"          if out =~ /not a directory/
        }
      rescue
        raise Connector::AuthenticationFailed, "login refused by '#{@address}'"            if $!.message =~ /NT_STATUS_LOGON_FAILURE/
        raise Connector::AuthenticationFailed, "login refused by '#{@address}'"            if $!.message =~ /NT_STATUS_ACCESS_DENIED/
        raise Connector::HostUnreachable, "host unreachable '#{@address}'"                 if $!.message =~ /NT_STATUS_UNSUCCESSFUL/
        raise Connector::HostUnreachable, "host unreachable '#{@address}'"                 if $!.message =~ /NT_STATUS_HOST_UNREACHABLE/
        raise Connector::BadNetworkName, "bad network name '#{@address}'"                  if $!.message =~ /NT_STATUS_BAD_NETWORK_NAME/
        raise Connector::NetworkUnreachable, "network unreachable '#{@address}'"           if $!.message =~ /NT_STATUS_NETWORK_UNREACHABLE/
        raise Connector::ConnectionRefused, "connection refused by '#{@address}'"          if $!.message =~ /NT_STATUS_CONNECTION_REFUSED/
        raise Connector::ConnectionRefused, "connection refused by '#{@address}'"          if $!.message =~ /Receiving SMB: Server \S+ stopped responding/
        raise Connector::NoSuchFileOrDirectory, "cannot open file or directory '#{file}'"  if $!.message =~ /NT_STATUS_OBJECT_(NAME|PATH)_NOT_FOUND/
        raise Connector::NoSuchFileOrDirectory, "cannot open file or directory '#{file}'"  if $!.message =~ /NT_STATUS_NO_SUCH_FILE/
        raise Connector::PermissionDenied, "permission denied for file '#{file}'"          if $!.message =~ /NT_STATUS_ACCESS_DENIED/
        raise Connector::PermissionDenied, "permission denied for file '#{file}'"          if $!.message =~ /NT_STATUS_CANNOT_DELETE/
        raise Connector::PermissionDenied, "permission denied for file '#{path}'"          if $!.message =~ /NT_STATUS_ACCOUNT_DISABLED/
        raise Connector::PermissionDenied, "permission denied for file '#{path}'"          if $!.message =~ /NT_STATUS_ACCOUNT_LOCKED/
        raise Connector::PermissionDenied, "permission denied for file '#{path}'"          if $!.message =~ /not a directory/
        raise $!
      ensure
        File.delete(@authfile) if File.exist?(@authfile)
      end
    end

    def rename(src, dst)
      begin
        File.open(@authfile, 'w', 0600) { |fd| fd.puts "username = #{@login}\npassword = #{@password}\n" }
        STDERR.puts "->renaming #{src} to #{dst}" if @debug

        cmd = ''
        src.split('/')[0...-1].each { |item|
          next if item == ''
          next if item == src.split('/')[1] # share
          cmd = cmd + "cd \\\"#{item}\\\"\n"
        }

        cmd = cmd + "rename \\\"#{src.split('/').last}\\\" \\\"#{dst.split('/').last}\\\"\n"
        @sys.exec("echo \"#{cmd}\" | smbclient -E -g -A #{@authfile} -p #{@port} //#{@address}/#{@share} 2>&1").split("\n").each { |out|
          raise Connector::AuthenticationFailed, "login refused by '#{@address}'"            if out =~ /NT_STATUS_LOGON_FAILURE/
          raise Connector::AuthenticationFailed, "login refused by '#{@address}'"            if out =~ /NT_STATUS_ACCESS_DENIED/
          raise Connector::HostUnreachable, "host unreachable '#{@address}'"                 if out =~ /NT_STATUS_UNSUCCESSFUL/
          raise Connector::HostUnreachable, "host unreachable '#{@address}'"                 if out =~ /NT_STATUS_HOST_UNREACHABLE/
          raise Connector::BadNetworkName, "bad network name '#{@address}'"                  if out =~ /NT_STATUS_BAD_NETWORK_NAME/
          raise Connector::NetworkUnreachable, "network unreachable '#{@address}'"           if out =~ /NT_STATUS_NETWORK_UNREACHABLE/
          raise Connector::ConnectionRefused, "connection refused by '#{@address}'"          if out =~ /NT_STATUS_CONNECTION_REFUSED/
          raise Connector::ConnectionRefused, "connection refused by '#{@address}'"          if out =~ /Receiving SMB: Server \S+ stopped responding/
          raise Connector::NoSuchFileOrDirectory, "cannot open file or directory '#{dst}'"   if out =~ /NT_STATUS_OBJECT_(NAME|PATH)_NOT_FOUND/
          raise Connector::NoSuchFileOrDirectory, "cannot open file or directory '#{dst}'"   if out =~ /NT_STATUS_NO_SUCH_FILE/
          raise Connector::PermissionDenied, "permission denied for file '#{dst}'"           if out =~ /NT_STATUS_ACCESS_DENIED/
          raise Connector::FileAlreadyExist, "file already exist '#{dst}'"                   if out =~ /NT_STATUS_OBJECT_NAME_COLLISION/
          raise Connector::PermissionDenied, "permission denied for file '#{path}'"          if out =~ /NT_STATUS_ACCOUNT_DISABLED/
          raise Connector::PermissionDenied, "permission denied for file '#{path}'"          if out =~ /NT_STATUS_ACCOUNT_LOCKED/
          raise Connector::PermissionDenied, "unknown error '#{out}'"                        if out =~ /session setup failed/
          raise Connector::PermissionDenied, "permission denied for file '#{path}'"          if out =~ /not a directory/
        }
      rescue
        raise Connector::AuthenticationFailed, "login refused by '#{@address}'"            if $!.message =~ /NT_STATUS_LOGON_FAILURE/
        raise Connector::AuthenticationFailed, "login refused by '#{@address}'"            if $!.message =~ /NT_STATUS_ACCESS_DENIED/
        raise Connector::HostUnreachable, "host unreachable '#{@address}'"                 if $!.message =~ /NT_STATUS_UNSUCCESSFUL/
        raise Connector::HostUnreachable, "host unreachable '#{@address}'"                 if $!.message =~ /NT_STATUS_HOST_UNREACHABLE/
        raise Connector::BadNetworkName, "bad network name '#{@address}'"                  if $!.message =~ /NT_STATUS_BAD_NETWORK_NAME/
        raise Connector::NetworkUnreachable, "network unreachable '#{@address}'"           if $!.message =~ /NT_STATUS_NETWORK_UNREACHABLE/
        raise Connector::ConnectionRefused, "connection refused by '#{@address}'"          if $!.message =~ /NT_STATUS_CONNECTION_REFUSED/
        raise Connector::ConnectionRefused, "connection refused by '#{@address}'"          if $!.message =~ /Receiving SMB: Server \S+ stopped responding/
        raise Connector::NoSuchFileOrDirectory, "cannot open file or directory '#{dst}'"   if $!.message =~ /NT_STATUS_OBJECT_(NAME|PATH)_NOT_FOUND/
        raise Connector::NoSuchFileOrDirectory, "cannot open file or directory '#{dst}'"   if $!.message =~ /NT_STATUS_NO_SUCH_FILE/
        raise Connector::PermissionDenied, "permission denied for file '#{dst}'"           if $!.message =~ /NT_STATUS_ACCESS_DENIED/
        raise Connector::FileAlreadyExist, "file already exist '#{dst}'"                   if $!.message =~ /NT_STATUS_OBJECT_NAME_COLLISION/
        raise Connector::PermissionDenied, "permission denied for file '#{path}'"          if $!.message =~ /NT_STATUS_ACCOUNT_DISABLED/
        raise Connector::PermissionDenied, "permission denied for file '#{path}'"          if $!.message =~ /NT_STATUS_ACCOUNT_LOCKED/
        raise Connector::PermissionDenied, "permission denied for file '#{path}'"          if $!.message =~ /not a directory/
        raise $!
      ensure
        File.delete(@authfile) if File.exist?(@authfile)
      end
    end

    def list(path)
      begin
        File.open(@authfile, 'w', 0600) { |fd| fd.puts "username = #{@login}\npassword = #{@password}\n" }
        STDERR.puts "->listing #{path}" if @debug
        lines = []

        cmd = ''
        path.split('/').each { |item|
          next if item == ''
          next if item == path.split('/')[1] # share
          cmd = cmd + "cd \\\"#{item}\\\"\n"
        }


        cmd = cmd + "dir\n"
        @sys.exec("echo \"#{cmd}\" | smbclient -E -g -A #{@authfile} -p #{@port} //#{@address}/#{@share} 2>&1").split("\n").each { |out|
          raise Connector::AuthenticationFailed, "login refused by '#{@address}'"            if out =~ /NT_STATUS_LOGON_FAILURE/
          raise Connector::AuthenticationFailed, "login refused by '#{@address}'"            if out =~ /NT_STATUS_ACCESS_DENIED/
          raise Connector::HostUnreachable, "host unreachable '#{@address}'"                 if out =~ /NT_STATUS_UNSUCCESSFUL/
          raise Connector::HostUnreachable, "host unreachable '#{@address}'"                 if out =~ /NT_STATUS_HOST_UNREACHABLE/
          raise Connector::BadNetworkName, "bad network name '#{@address}'"                  if out =~ /NT_STATUS_BAD_NETWORK_NAME/
          raise Connector::NetworkUnreachable, "network unreachable '#{@address}'"           if out =~ /NT_STATUS_NETWORK_UNREACHABLE/
          raise Connector::ConnectionRefused, "connection refused by '#{@address}'"          if out =~ /NT_STATUS_CONNECTION_REFUSED/
          raise Connector::ConnectionRefused, "connection refused by '#{@address}'"          if out =~ /Receiving SMB: Server \S+ stopped responding/
          raise Connector::NoSuchFileOrDirectory, "cannot open file or directory '#{path}'"  if out =~ /NT_STATUS_OBJECT_(NAME|PATH)_NOT_FOUND/
          raise Connector::NoSuchFileOrDirectory, "cannot open file or directory '#{path}'"  if out =~ /NT_STATUS_NO_SUCH_FILE/
          raise Connector::NoSuchFileOrDirectory, "cannot open file or directory '#{path}'"  if out =~ /NT_STATUS_OBJECT_NAME_INVALID/
          raise Connector::PermissionDenied, "permission denied for file '#{path}'"          if out =~ /NT_STATUS_ACCESS_DENIED/
          raise Connector::PermissionDenied, "permission denied for file '#{path}'"          if out =~ /NT_STATUS_ACCOUNT_DISABLED/
          raise Connector::PermissionDenied, "permission denied for file '#{path}'"          if out =~ /NT_STATUS_ACCOUNT_LOCKED/
          raise Connector::PermissionDenied, "unknown error '#{out}'"                        if out =~ /session setup failed/
          raise Connector::PermissionDenied, "permission denied for file '#{path}'"          if out =~ /not a directory/
          lines << out
        }

        items = []
        lines[0..(lines.length - 2)].each { |line|
          next if line !~ /^\s{2}\S+/
          next if line =~ /^\s{2}\.+/

          words = line.split(' ')
          next if words.length > 7 and words[(words.length - 7)].split('').include?('D')


          file  = line[0..(line.length - 40)].strip
          size  = words[(words.length - 6)]
          mtime = Time.at(Time.parse((words[(words.length - 5)..(words.length - 1)]).join(' ')).to_i).to_s

          i = {}
          i['name']  = file
          i['size']  = size
          i['mtime'] = mtime
          items << i
        }

        return items.sort { |a, b| a['name'] <=> b['name'] }
      rescue
        raise Connector::AuthenticationFailed, "login refused by '#{@address}'"            if $!.message =~ /NT_STATUS_LOGON_FAILURE/
        raise Connector::AuthenticationFailed, "login refused by '#{@address}'"            if $!.message =~ /NT_STATUS_ACCESS_DENIED/
        raise Connector::HostUnreachable, "host unreachable '#{@address}'"                 if $!.message =~ /NT_STATUS_UNSUCCESSFUL/
        raise Connector::HostUnreachable, "host unreachable '#{@address}'"                 if $!.message =~ /NT_STATUS_HOST_UNREACHABLE/
        raise Connector::BadNetworkName, "bad network name '#{@address}'"                  if $!.message =~ /NT_STATUS_BAD_NETWORK_NAME/
        raise Connector::NetworkUnreachable, "network unreachable '#{@address}'"           if $!.message =~ /NT_STATUS_NETWORK_UNREACHABLE/
        raise Connector::ConnectionRefused, "connection refused by '#{@address}'"          if $!.message =~ /NT_STATUS_CONNECTION_REFUSED/
        raise Connector::ConnectionRefused, "connection refused by '#{@address}'"          if $!.message =~ /Receiving SMB: Server \S+ stopped responding/
        raise Connector::NoSuchFileOrDirectory, "cannot open file or directory '#{path}'"  if $!.message =~ /NT_STATUS_OBJECT_(NAME|PATH)_NOT_FOUND/
        raise Connector::NoSuchFileOrDirectory, "cannot open file or directory '#{path}'"  if $!.message =~ /NT_STATUS_NO_SUCH_FILE/
        raise Connector::NoSuchFileOrDirectory, "cannot open file or directory '#{path}'"  if $!.message =~ /NT_STATUS_OBJECT_NAME_INVALID/
        raise Connector::PermissionDenied, "permission denied for file '#{path}'"          if $!.message =~ /NT_STATUS_ACCESS_DENIED/
        raise Connector::PermissionDenied, "permission denied for file '#{path}'"          if $!.message =~ /NT_STATUS_ACCOUNT_DISABLED/
        raise Connector::PermissionDenied, "permission denied for file '#{path}'"          if $!.message =~ /NT_STATUS_ACCOUNT_LOCKED/
        raise Connector::PermissionDenied, "permission denied for file '#{path}'"          if $!.message =~ /not a directory/
        raise $!
      ensure
        File.delete(@authfile) if File.exist?(@authfile)
      end
    end
  end
end
