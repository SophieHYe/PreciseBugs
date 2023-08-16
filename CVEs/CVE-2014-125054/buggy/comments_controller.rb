class CommentsController < ApplicationController
	  before_action :logged_in_user, only: [:create, :destroy]

  def new
  	@comment = Comment.new(parent_id: params[:parent_id])

  	respond_to do |format|
  	 format.html
  	 format.js
  	end
  end

  def create
  	@post = Post.find(session[:current_post_id])

  	if params[:parent_id].to_i > 0
    parent = Comment.find_by_id(params[:parent_id])
    @comment = parent.comments.build(comment_params)
    @comment.user_id = session[:user_id]
    else
    @comment = @post.comments.build(comment_params)
    @comment.user_id = session[:user_id]
    end

    if @comment.save
      flash[:success] = "Comment created!"
      redirect_to @post
    else
      @feed_items = []
				render 'new'
	    end
  end

  def destroy
  	@post = Post.find(session[:current_post_id])
  	@comment = Comment.find(params[:id])
    @comment.destroy
    flash[:success] = "Comment deleted"
    redirect_to @post
  end

# VOTES
  def upvote
    @comment = Comment.find(params[:id])
    if current_user_existing_vote.nil?
      @vote = @comment.votes.create
      @vote.update_attributes(isUpvote: true, user_id: current_user.id)
      @comment.user.increment_karma(1)
      redirect_to request.referrer
    else
      current_user_existing_vote.update_attribute(:isUpvote, true)
      #changing existing vote from -1 to a +1, so karma adjusted by 2
      @comment.user.increment_karma(2)
      redirect_to request.referrer
    end
  end

  def unvote
    if current_user_existing_vote.nil?
      # nothing to change since no existing vote
      redirect_to request.referrer
    else
      @comment = Comment.find(params[:id])
      # first find out whether the existing vote was +1 or -1
      if current_user_existing_vote.isUpvote
        @comment.user.increment_karma(-1)
      else
        @comment.user.increment_karma(1)
      end
      current_user_existing_vote.destroy
      redirect_to request.referrer
    end
  end

  def downvote
    @comment = Comment.find(params[:id])
    if current_user_existing_vote.nil?
      @vote = @comment.votes.create
      @vote.update_attributes(isUpvote: false, user_id: current_user.id)
      @comment.user.increment_karma(-1)
      redirect_to request.referrer
    else
      current_user_existing_vote.update_attribute(:isUpvote, false)
      #changing existing vote from +1 to a -1, so karma adjusted by -2
      @comment.user.increment_karma(-2)
      redirect_to request.referrer
    end  
  end

  private

    def comment_params
      params.require(:comment).permit(:content)
    end

    def current_user_existing_vote
      @vote = current_user.votes.find_by(comment_id: params[:id])
    end
end
