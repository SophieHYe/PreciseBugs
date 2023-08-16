json.grades @grades do |grade_obj|
  json.id grade_obj.id
  json.grade grade_obj.grade
  json.id grade_obj.assignment_id
  json.user_id grade_obj.user_id
  json.title grade_obj.assignment.title
  json.description grade_obj.assignment.description
end

json.student_fname @student.fname
json.student_lname @student.lname
json.course_id @course_id

#remember then that for a single model, only top-level attrs will be assigned
# for a collection, each entry in the array should be top-level attrs (or wrapped in only a single object wrapper)
# but the array itself must be top-level
# weird: Cannot mix json.array! with other top-level attrs
