//Lists a students grade - an admin has access to this view to edit grades
MerlinsBoard.Views.GradesStudent = Backbone.View.extend({
  initialize: function () {
    this.listenTo(this.collection, "add change:grade remove sync", this.render)
    _.bindAll(this, "gradeSaveCallback", "gradeSaveErrorCallback")
    //for jbuidler - nest each of a student's grade under them along with basic information about the assignmen
  },

  template: JST["grades/grades-student-list"],

  events: {
    "click .grade-number":"editGrade",
    "blur .grade-input": "saveGrade"
  },

  className: "grade-list",

  tagName: "section",

  render: function () {
    var renderedContent = this.template({grades: this.collection, student: this.collection.student()});
    this.$el.html(renderedContent);
    return this
  },

  editGrade: function (event) {
    var gradeString = $(event.currentTarget).val();
    var num = parseInt(gradeString);
    var $input = $("<input type='number' min='0' step='1' max='100'>").addClass('grade-input').val(num);
    this.modelNumber = $(event.currentTarget).data('id');
    $(".grade-number[data-id=".concat(this.modelNumber,"]")).html($input)
  },

  saveGrade: function (event) {
    var editedGrade = this.collection.getOrFetch(this.modelNumber);
    var newGrade = parseInt($('input.grade-input').val());
    var courseID = this.collection.course_id;

    debugger

    editedGrade.set({grade: newGrade});
    editedGrade.save({course_id: courseID},{success: this.gradeSaveCallback(editedGrade),
    error: this.gradeSaveErrorCallback
    });
  },

  gradeSaveCallback: function (editedGrade) {
    this.collection.fetch(); //unideal - needs to be banished with composite view paradigm.
    // this.collection.add(editedGrade,{merge: true});
  },

  gradeSaveErrorCallback: function (model, resp) {

    var errorArray = resp.responseJSON
    var $errorList = $("<ul>").addClass('errors');
    _.each(errorArray, function (error) {
      var $error = $("<li>").text(error).addClass('error');
      $errorList.append($error);
    })

    $("section.grade-errors").html($errorList);
  }
})
