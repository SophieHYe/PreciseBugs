# This file should contain all the record creation needed to seed the database with its default values.
# The data can then be loaded with the rake db:seed (or created alongside the db with db:setup).
#
# Examples:
#
#   cities = City.create([{ name: 'Chicago' }, { name: 'Copenhagen' }])
#   Mayor.create(name: 'Emanuel', city: cities.first)

#should create master user that has most functionality available to it.

User.create(fname:"Jonathan", lname: "Lee", email: "l33.jonathan@gmail.com", password: "testing")
User.create(fname:"John", lname: "Doe", email: "johndoe@gmail.com",
password: "testing")

(1..10).each do |user_no|
  User.create(
    fname: Faker::Name.first_name,
    lname: Faker::Name.last_name,
    email: Faker::Internet.email,
    password: Faker::Internet.password(8),
  )
end

weekdays = ["Monday","Tuesday","Wednesday","Thursday","Friday"]

(1..5).each do |course_no|
  time_string = "12:0" + course_no.to_s
  end_time_string = "12:0" + (course_no+2).to_s

  Course.create(
    name: Faker::Lorem.word,
    location: Faker::Address.street_address,
    day: weekdays[rand(5)],
    description: Faker::Lorem.sentence,
    start_time: time_string,
    end_time: end_time_string
  )
end


#enrollment
[1,3].each do |odd|
  CoursesStudents.create(user_id: 1, course_id: odd)
end

2.times{
  (2..11).each do |enroll|
    course_no = (rand(5)+1)
    student_no = enroll

    CoursesStudents.create( #to make this less haphazard, I could just iterate over the courses and users and match up it to avoid collisions.
      user_id: student_no,
      course_id: course_no
    )
  end
}

[2,4].each do |even|
  CoursesInstructors.create(user_id: 1, course_id: even)
end

(2..11).each do |teacher| #not getting hit enough times, need to rejigger to avoid conflicts or just increase number to increase chances of seeding database
  course_no = (rand(5)+1)
  instructor_no = (rand(11)+1)

  CoursesInstructors.create(
    user_id: instructor_no,
    course_id: course_no
  )
end

#for announcements and assignments, need to set up inverse relationship... maybe. Will need to think about it for a bit

CoursesInstructors.all.each do |admin_link|
  course_no = admin_link.course_id
  admin_id = admin_link.user_id

  3.times {
    Announcement.create(
      title: Faker::Lorem.word.capitalize,
      body: Faker::Lorem.paragraph,
      user_id: admin_id,
      course_id: course_no,
    )
  }

end

Course.all.each do |course|
  2.times {
    Assignment.create(
      title: Faker::Lorem.word.capitalize,
      description: Faker::Lorem.sentence,
      due_date: Faker::Date.forward(10), #should find someway to exclude weekends
      course_id: course.id
    )
  }
end

#grades

CoursesStudents.all.each do |student_link|
  course_id = student_link.course_id
  user_id = student_link.user_id

  course = Course.find(course_id)

  course.assignments.each do |assignment|
    Grade.create(user_id: user_id, assignment_id: assignment.id, grade: rand(101))
  end
  # A note- creating entries haphazardly like this may cause there to be an grade for an class assignment that user doesn't even attend - in this particular case it can't happen, but its in the realm of possibility
end
