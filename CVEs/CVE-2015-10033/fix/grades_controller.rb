class Api::GradesController < Api::ApiController
  before_action(only: [:update, :show]) {admins_only(params["course_id"])}
  before_action :is_user_or_instructor?, only: [:index]

  def index
    #I may want an internal control here instead of using the before_action...
    @grades = Grade.includes(:assignment,:course,:user).where("user_id = ?", params["user_id"])
    @student = @grades.first.user
    @course_id = params["course_id"].to_i

    @grades = @grades.select {|grade| grade.course.id == params["course_id"].to_i}
  end

  #Neither of these may be needed because they should only be created/destroyed depending on the assignment

  # def destroy
  #   @grade = Grade.find(params[:id])
  #   @grade.destroy
  #   render json: {}
  # end
  # def create
  #   @grade = Grade.new(grade_params)
  #
  #   if @grade.save
  #     render json: @grade
  #   else
  #     render json: @grade.errors.full_messages, status: 422
  #   end
  # end

  def show
    @grade = Grade.find(params[:id])
    render json: @grade
  end
  #will need to create unique validator to ensure congruency between course auth and resource id course....
  def update
    @grade = Grade.find(params[:id])

    if @grade.update(grade_params)
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
    params.permit(:grade, :assignment_id, :user_id) #need to change grade column - it confuses params_wrapper
  end

end
