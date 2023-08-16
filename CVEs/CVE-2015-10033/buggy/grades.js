MerlinsBoard.Collections.Grades = Backbone.Collection.extend({
  initialize: function (options) {
    // this.owner = options["owner"];
    this.course_id = options["course_id"]
    this.url = "api/users/" + options["user_id"] + "/grades"
  },

  model: MerlinsBoard.Models.Grade,

  getOrFetch: function (id) {
    var grade = this.get(id);
    var grades = this;

    if (!grade) {
      grade = new MerlinsBoard.Models.Grade({id: id});
      grade.fetch({ success: function () {
        this.add(grade);
      }
      })
    } else {
      grade.fetch();
    }

    return grade
  },

  fetch: function(options) {
    if (!options) {
      options = {};
    }

    //some logic here to check if "data" was already passed in, and fusing that to the data parameter...

    _.extend(options,{ data: $.param({ course_id: this.course_id}) }); //options is changed
    //with this, I might always have to bind fetch - be mindful of this in case I need to fetch more data
    return Backbone.Collection.prototype.fetch.call(this, options);
  },

  parse: function (resp) {
    this.student = new MerlinsBoard.Models.User({fname: resp.student_fname,lname: resp.student_lname});

    resp.student_fname.delete //is there a better way to clean this up?
    resp.student_fname.delete
    return resp.grades
  }

})
