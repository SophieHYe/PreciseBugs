class ImageController < ApplicationController
  def show
    @images = Image.where(sol: params[:sol])
  end

  def index
    # find the martial solar date of the most recent image taken
    # @most_recent = Image.all.sort_by { |hash| -hash[:sol].to_i }.first[:sol]
    @image_count = Image.count
    @most_recent = Image.maximum(:sol)
  end
end
