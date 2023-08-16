module TwitterBot
  module Plugin
    class TwitterAnnouncer
      TWITTER_NAME_REGEXP = '^[a-zA-Z_0-9]{1,15}$'
      include Cinch::Plugin

      def initialize(*args)
        super

        raise 'Invalid plugin configuration for twitter. Missing API data' unless check_config?

        tw = @bot.config.twitter
        @twitter = Twitter::REST::Client.new do |config|
          config.consumer_key = tw['consumer_key']
          config.consumer_secret = tw['consumer_secret']
          config.access_token = tw['access_token']
          config.access_token_secret = tw['access_token_secret']
        end

        @format = Format(:bold, '%{account}') + ': "%{tweet}" ' + Format(:italic, '(%{time})') + ' at %{uri}'
        @timer = Timer(tw['timer'] || 300, method: :check_tweets)
        @history = []
        @enabled = []

        @bot.config.twitmap.each do |chan, _account|
          @enabled << chan.downcase
        end

        # Cache tweets that have already happened. Avoid broadcasting them to the channel each reboot.
        check_tweets(true)
      end

      match Regexp.new('tw(?:eet)?check ([a-zA-Z]+)$'), method: :announce
      def announce(m, option)
        option.downcase!

        if !m.channel.opped?(m.user) && option != 'status'
          m.user.notice('You are not authorized to use this command!')
          return
        end

        case option
          when 'on'
            enable(m, true)
          when 'off'
            enable(m, false)
          when 'status'
            status = announcing?(m.channel.name) ? 'Announcing new tweets.' : 'Not announcing.'

            accounts_str = ''
            @bot.config.twitmap.each do |chan, accounts|
              next if chan != m.channel.name.downcase

              accounts.each do |account|
                accounts_str += "#{account}, "
              end
            end
            accounts_str.sub!(/, $/, '')
            accounts_str = 'None!' unless accounts_str

            m.reply("Status for #{m.channel.name}: #{status}")
            m.reply("Checking for the following Twitter Accounts: #{accounts_str}")
          else
            m.reply("Option [#{option}] not understood. Options: on, off, status")
        end
      end

      match Regexp.new('tw(?:eet)?list ([a-zA-Z]+)(?: ([^ ]+))?$'), method: :twlist
      def twlist(m, option, param)
        return m.user.notice('You are not authorized to use this command!') unless m.channel.opped?(m.user)
        option.downcase!
        param = (param.nil? ? '' : param.downcase)
        chan = m.channel.name.downcase

        if !param.empty? && !valid_twitter_account?(param)
          return m.reply("The Account [#{param}] is not a valid Twitter account.")
        end
        param = 'help' if param.empty?

        case option
          when 'add'
            if @bot.config.twitmap[chan].include?(param)
              m.reply("I am already watching for activity from #{param}!")
            else
              @bot.config.twitmap[chan] << param
              m.reply("The Account [#{param}] is now being watched for activity. until I am restarted.")
            end
          when 'del', 'delete'
            if @bot.config.twitmap[chan].include?(param)
              @bot.config.twitmap[chan].delete(param)
              m.reply("The Account [#{param}] is no longer being watched for activity. until I am restarted.")
            else
              m.reply("I am not watching #{param} for activity!")
            end
          when 'help'
            m.reply('Options: add <account>, del <account>')
          else
            m.reply("Option [#{option}] not understood. Options: add <account>, del <account>")
        end
      end

      match Regexp.new('tw(?:eet)? ([^ ]+)(?: (\d))?'), method: :check_tweet
      def check_tweet(m, account, amount = 1)
        amount = 1 if amount < 1 && amount > 3
        tweets = get_tweets(account, amount)

        return m.reply("Sorry, but #{account} has no public tweets.") unless tweets

        tweets.each do |tweet|
          next unless tweet

          m.reply(fmt_tweet(tweet))
        end
      end

      def check_tweets(dry_run = false)
        all_tweets = []
        history = []

        @bot.config.twitter_accounts.each do |account|
          all_tweets << get_tweets(account)
        end

        all_tweets.each do |tweets|
          if !tweets || tweets.count == 0
            warn 'Tweets are empty, skipping batch.'
            next
          end

          @bot.config.twitmap.each do |chan, accounts|
            next unless @enabled.include?(chan)

            tweets.each do |tweet|
              accounts.each do |account|
                next unless tweet[:account].downcase == account.downcase

                history << chan.to_s + tweet[:id].to_s
                next if announced?(chan, tweet[:id])

                Channel(chan).send(fmt_tweet(tweet)) unless dry_run
              end
            end
          end
        end

        @history = history
      end

      def get_tweets(account, amount = 3)
        tweets = []

        begin
          @twitter.user_timeline(account, count: amount).each do |tweet|
            name = tweet.user.screen_name

            tweets << { account: name, tweet: tweet.text, time: tweet.created_at, uri: tweet.uri.to_s, id: tweet.id }
          end
        rescue StandardError => e
          warn "Unable to retrieve Tweet information for #{account}: #{e}"
          tweets = nil
        end

        tweets
      end

      def enable(m, option)
        chan = m.channel.name.downcase

        if option
          if announcing?(chan)
            m.reply('I am already announcing to this channel!')
          else
            @enabled << chan
            m.reply("I am now announcing to #{m.channel.name}, until I am restarted, or this option is toggled off.")
          end
        else
          if announcing?(chan)
            @enabled.delete(chan)
            m.reply("No longer announcing to #{m.channel.name}, until I am restarted, or this option is toggled back on.")
          else
            m.reply('I am already not announcing to this channel!')
          end
        end
      end

      def fmt_tweet(tweetinfo)
        account = fmt_account(tweetinfo[:account])
        tweet = tweetinfo[:tweet]
        time = fmt_time(tweetinfo[:time])
        uri = tweetinfo[:uri]

        @format % { account: account, tweet: tweet, time: time, uri: uri }
      end

      def fmt_account(s)
        s = '@' + s unless s.start_with?('@')

        s
      end

      def fmt_time(s)
        now = Time.now.to_i
        t = Time.at(s)
        since = now - t.to_i

        if since <= 3540 && since >= 0
          if since > 60
            since = (since / 60).round
            time_str = 'minute'
          else
            time_str = 'second'
          end

          time_str += 's' if since != 1
          return "#{since} #{time_str} ago"
        end

        t.to_datetime.strftime('%Y-%m-%d %H:%M:%S%Z')
      end

      def announced?(channel, id)
        @history.include?(channel.to_s + id.to_s)
      end

      def announcing?(channel)
        @enabled.include?(channel.downcase)
      end

      def check_config?
        twconf = @bot.config.twitter

        keys = %w(consumer_key consumer_secret access_token access_token_secret)
        keys.each do |k|
          return false if !twconf.key?(k) ||
                          (twconf.key?(k) && twconf[k].to_s.empty?) ||
                          twconf[k].start_with?('Your_')
        end

        true
      end

      def valid_twitter_account?(account)
        account[0] = '' if account.start_with?('@')

        /#{TWITTER_NAME_REGEXP}/.match(account) != nil
      end
    end
  end
end
