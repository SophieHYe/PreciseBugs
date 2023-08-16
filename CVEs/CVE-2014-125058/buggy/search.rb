  ### Yup -- having this method focus solely on
  ###        returning a collection of People
  ###        allows you to reuse its output for
  ###        different purposes (such as passing
  ###        it to the #list method you defined)
	def search_first_name
		print "First name: "
		first_name = gets.chomp 
		people = Person.where("first_name LIKE '%#{first_name}%'")
		return people
	end

	def search_last_name
		print "Last name: "
		last_name = gets.chomp 
		people = Person.where("last_name LIKE '%#{last_name}%'")
		return people
	end

  ### TODO: Have this method return a collection
  ###       of People so that its output can be
  ###       passed to the #list method.
	def search_email
		print "\nEmail: "
		email = gets.chomp 
		emails = Email.where("email LIKE '%#{email}%'")
		if emails.empty?
			puts "\nNo entries found\n\n"
		else
			puts "\n#{emails.count} emails found…\n\n"
			emails.each do |email|
				puts "#{email.person.first_name} #{email.person.last_name}: #{email.email}"
			end
		end
	end

	def list(people)
		if people.empty?
			puts "\nNo entries found\n\n"
		else
			people.each do |entry|
				puts "#{entry.first_name} #{entry.last_name}"
				puts "Phone numbers:"
				entry.phones.each do |phone|
					puts "#{phone.label}: #{phone.phone}"
				end
				puts "Emails:"
				entry.emails.each do |email|
					puts "#{email.label}: #{email.email}"
				end
			end
		end
	end

def search

	menu = 0
	puts "\n"
	puts "-+-+-+-Search-+-+-+-"
	puts "1 - …by first name"
	puts "2 - …by last name"
	puts "3 - …by email"
	puts "4 - Return to main menu"
	print "\n? "
	menu = gets.chomp

	begin 
		select  = Integer(menu)
		if select == 1
			result = search_first_name
			list(result)
		elsif select == 2
			result = search_last_name
			list(result)
		elsif select == 3
      ### TODO: Modify the search_email method so that
      ###       it returns a collection of People.  That
      ###       way, you could pass the result to the
      ###       #list method.
			result = search_email
		elsif select == 4
			puts "\nReturning to main menu…\n\n"
		else
			puts "\nStop wasting time!\n\n"

      ### TODO: This will return to the main menu.
      ###       How would you change the code so
      ###       that it remains on the current menu?
		end
	rescue ArgumentError
		puts "\nStop wasting time, use a number!\n\n"

    ### TODO: This will return to the main menu.
    ###       How would you change the code so
    ###       that it remains on the current menu?
	end

end