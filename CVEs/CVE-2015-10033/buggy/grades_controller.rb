class Api::GradesController < Api::ApiController
  before_action(except: [:index]) {admins_only(params["course_id"])} #wil always need to pass this in on every fetch
  before_action :is_user_or_instructor?, only: [:index]

  def index
    #I may want an internal control here instead of using the before_action...
    @grades = Grade.includes(:assignment,:course,:user).where("user_id = ?", params["user_id"])
    @student = @grades.first.user
    @grades = @grades.select {|grade| grade.course.id == params["course_id"].to_i}
  end

  def destroy
    @grade = Grade.find(params[:id])
    @grade.destroy
    render json: {}
  end

  # def create
  #   @grade = Grade.new(grade_params)
  #
  #   if @grade.save
  #     render json: @grade
  #   else
  #     render json: @grade.errors.full_messages, status: 422
  #   end
  # end

  def update
    @grade = Grade.find(params[:id])

    if @grade.save
      render json: @grades
    else
      render json: @grade.errors.full_messages, status: 422
    end
  end

  private

  def is_user_or_instructor?
    return if current_user.id == params["user_id"].to_i
    admins_only(params["course_id"])
  end

  def grade_params
    params.require(:grade).permit(:grade, :assignment_id, :user_id)
  end
  
end
