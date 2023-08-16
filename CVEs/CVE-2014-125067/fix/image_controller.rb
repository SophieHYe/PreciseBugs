class ImageController < ApplicationController
  def show
    @images = Image.where(:sol => params[:sol])
  end

  def index
    # find the martian solar date of the most recent image taken
    @image_count = Image.count
    @most_recent = Image.maximum(:sol)
  end
end
