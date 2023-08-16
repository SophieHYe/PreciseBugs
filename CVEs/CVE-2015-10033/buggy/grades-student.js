//Lists a students grade - an admin has access to this view to edit grades
MerlinsBoard.Views.GradesStudent = Backbone.View.extend({
  initialize: function () {
    this.listenTo(this.collection, "add change:grade remove", this.render)
    _.bindAll(this, "gradeSaveCallback", "gradeSaveErrorCallback")
    //for jbuidler - nest each of a student's grade under them along with basic information about the assignment
  },

  template: JST["grades/grades-student"],

  events: {
    "click .grade-number":"editGrade",
    "blur .grade-input": "saveGrade"
  },

  className: "grade-list",

  tagName: "section",

  render: function () {
    var renderedContent = this.template({grades: this.collection});
    this.$el.html(renderedContent);
    return this.$el
  },

  editGrade: function (event) {
    var gradeString = $(event.currentTarget).text();
    var num = parseInt(gradeString);
    var $input = $("<input type='number'>").addClass('grade-input').val(num);

    this.modelNumber = $(event.currentTarget).data('id');

    $(".grade-number").html(input)
  },

  saveGrade: function (event) {
    var editedGrade = this.collection.getOrFetch(this.modelNumber);
    var newGrade = $('input.grade-input').val();
    var courseID = this.collection.course_id;

    editedGrade.set({grade: newGrade});
    //two options, send in the params with the model and strong params takes care of it
    //or send it in as an option for the save option
    editedGrade.save({},{success: this.gradeSaveCallback,
    error: this.gradeSaveErrorCallback,
    data: $.param({course_id: courseID})
    });
  },

  gradeSaveCallback: function () {
    this.collection.add(editedGrade,{merge: true})//this should be a closure - also editedGrade I think should persist as a variable...
    // $(".grade-number").html(editedGrade.get('grade')); this wont work because I'm inspecific, but I may not need it anyway to rerender
  },

  gradeSaveErrorCallback: function (model, response) {
    var errorArray = resp.responseJSON
    var $errorList = $("<ul>").addClass('errors');
    _.each(errorArray, function (error) {
      var $error = $("<li>").text(error).addClass('error');
      $errorList.append($error);
    })

    $("section.grade-errors").html($errorList);
  }
})
