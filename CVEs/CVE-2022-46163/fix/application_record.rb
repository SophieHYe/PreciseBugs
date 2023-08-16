class ApplicationRecord < ActiveRecord::Base
  self.abstract_class = true

  def self.ransackable_attributes(_)
    super & %w[end_date start_date name description event_id state]
  end

  def self.ransackable_associations(_)
    []
  end
end
