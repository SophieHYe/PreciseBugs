require_relative 'spec_helper'

describe "Adding an injury" do
  before do
    injury = Injury.new("Decapitation")
    injury.save
  end
  context "adding a unique injury" do
    let!(:output){ run_ltk_with_input("2", "Disembowelment") }
    it "should print a confirmation message" do
      output.should include("Disembowelment has been added.")
      Injury.count.should == 2
    end
    it "should insert a new injury" do
      Injury.count.should == 2
    end
    it "should use the name we entered" do
      Injury.last.name.should == "Disembowelment"
    end
  end
  context "adding a duplicate injury" do
    let(:output){ run_ltk_with_input("2", "Decapitation") }
    it "should print an error message" do
      output.should include("Decapitation already exists.")
    end
    it "should ask them to try again" do
      menu_text = "What is the injury you want to add?"
      output.should include_in_order(menu_text, "already exists", menu_text)
    end
    it "shouldn't save the duplicate" do
      Injury.count.should == 1
    end
    context "and trying again" do
      let!(:output){ run_ltk_with_input("2", "Decapitation", "Leprosy") }
      it "should save a unique item" do
        Injury.last.name.should == "Leprosy"
      end
      it "should print a success message at the end" do
        output.should include("Leprosy has been added")
      end
    end
  end
  context "entering an invalid looking injury name" do
    context "with SQL injection" do
      let(:input){ "phalangectomy'), ('425" }
      let!(:output){ run_ltk_with_input("2", input) }
      it "should create the injury without evaluating the SQL" do
        Injury.last.name.should == input
      end
      it "shouldn't create an extra injury" do
        Injury.count.should == 2
      end
      it "should print a success message at the end" do
        output.should include("#{input} has been added")
      end
    end
    context "without alphabet characters" do
      let(:output){ run_ltk_with_input("2", "4*25") }
      it "should not save the injury" do
        pending
        Injury.count.should == 1
      end
      it "should print an error message" do
        pending
        output.should include("'4*25' is not a valid injury name, as it does not include any letters'")
      end
      it "should let them try again" do
        pending
        menu_text = "What is the injury you want to add?"
        output.should include_in_order(menu_text, "not a valid", menu_text)
      end
    end
  end
end
