class Injury
  attr_reader :errors
  attr_reader :name

  def initialize(name)
    @name = name
    @errors = []
  end

  def self.all
    statement = "Select * from injuries;"
    execute_and_instantiate(statement)
  end

  def self.count
    statement = "Select count(*) from injuries;"
    result = Environment.database_connection.execute(statement)
    result[0][0]
  end

  def self.find_by_name(name)
    statement = "Select * from injuries where name = \"#{name}\";"
    execute_and_instantiate(statement)[0]
  end

  def self.last
    statement = "Select * from injuries order by id DESC limit(1);"
    execute_and_instantiate(statement)[0]
  end

  def save
    if Injury.find_by_name(self.name)
      @errors << "#{self.name} already exists."
      false
    else
      statement = "Insert into injuries (name) values ('#{name}');"
      Environment.database_connection.execute(statement)
      true
    end
  end

  private

  def self.execute_and_instantiate(statement)
    rows = Environment.database_connection.execute(statement)
    results = []
    rows.each do |row|
      results << Injury.new(row["name"])
    end
    results
  end
end
