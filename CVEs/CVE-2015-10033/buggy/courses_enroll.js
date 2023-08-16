MerlinsBoard.Views.CoursesEnroll = Backbone.View.extend({
	initialize: function () {
		this.coursesearchView = new MerlinsBoard.Views.CoursesSearch({collection: this.collection}); //render will put these in manually
		this.usercoursesView = new MerlinsBoard.Views.CoursesList({collection: this.model.courses()});
		this.usertaughtcoursesView = new MerlinsBoard.Views.CoursesList({collection: this.model.taughtcourses()});
	},
	
  template: JST['courses/enroll'],
	
	render: function () {
		this.$el.html(this.template());
    
    this.$("section.courses-attended").html(this.usercoursesView.render().$el);
    this.$("section.courses-taught").html(this.usertaughtcoursesView.render().$el);
    this.$("section.course-search").html(this.coursesearchView.render().$el);
		
    return this
	},
  
  //below two, again - hardcode URLs instead
																												
	show: function (event) {
		event.preventDefault();
		var id = $(event.currentTarget).data("id");
		Backbone.history.navigate("course/" + id + "/enroll", {trigger:true})
	},

	events: {
		"click a": "show"
	}

});
