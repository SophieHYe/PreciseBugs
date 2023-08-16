# Copyright (C) 2009-2012, InSTEDD
#
# This file is part of Nuntium.
#
# Nuntium is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# Nuntium is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with Nuntium.  If not, see <http://www.gnu.org/licenses/>.

require 'digest/md5'

class GeopollController < ApplicationController
  skip_filter :check_login

  # POST /:account_name/:channel_name/:secret_token/geopoll/incoming
  def incoming
    account = Account.find_by_id_or_name(params[:account_name])
    channel = account.geopoll_channels.find_by_name(params[:channel_name])
    auth_token = channel.configuration[:auth_token].to_s.split(' ')[1]
    identifier = params[:Identifier]
    signature = Digest::MD5.hexdigest(auth_token + identifier)

    if signature != params[:Signature]
      return render text: "Error", status: :unauthorized
    end

    msg = AtMessage.new
    msg.from = "sms://#{params[:SourceAddress]}"
    msg.to   = "sms://#{channel.configuration[:from]}"
    msg.body = params[:MessageText]
    msg.channel_relative_id = params[:Identifier]
    account.route_at msg, channel

    render text: "Accepted"
  end

  def status
    account = Account.find_by_id_or_name(params[:account_name])
    channel = account.geopoll_channels.find_by_name(params[:channel_name])
    ao = channel.ao_messages.find_by_channel_relative_id(params[:MessageId])

    unless ao
      return render text: "Error", status: :not_found
    end

    status = params[:Status]
    case status
      when "SUCCESS"
        ao.state = "confirmed"
      when "UNDELIVERABLE_TO_MESSAGING_PROVIDER", "REJECTED_BY_MESSAGING_PROVIDER",
        "RETRYABLE_FAILURE", "TERMINAL_FAILURE", "NOT_ROUTABLE"
        ao.state = "failed"
    end

    account.logger.info :channel_id => channel.id, :ao_message_id => ao.id,
      :message => "Recieved delivery notification with status #{status.inspect}"

     ao.custom_attributes[:geopoll_status] = status if status
     ao.save!

    render text: "Accepted"
  end

  def balance
    channel = account.geopoll_channels.find_by_id params[:channel_id]
    balance = Geopoll.check_balance(channel)
    render text: "Account balance is USD #{balance}"
  end
end
