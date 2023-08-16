MerlinsBoard.Views.SearchStudent = Backbone.View.extend({
  initialize: function () {
    this.listenTo(this.collection, "add remove reset", this.render)
  },

  className: "grades-studentsearch",

  template: JST["grades/grades-student-search"],

  render: function () {
    var renderedContent = this.template({students: this.collection});
    this.$el.html(renderedContent);
    return this.$el
  }
})
