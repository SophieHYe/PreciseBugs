MerlinsBoard.Views.CoursesSearch = Backbone.View.extend({
	initialize: function () {
		this.searchCollection = new MerlinsBoard.Collections.CoursesSearch();
	},

	template: JST["courses/coursesearch"],

	render: function () {
		var renderedContent = this.template({courses: this.collection});
		this.$el.html(renderedContent);
		return this
	},

  tagName: "section",

  className: "course-search",

	events: {
		"submit form.course-find":"search"
	},

	// search: function (event) {
	// 	event.preventDefault();
  //  var query = $("input.course-find-input").val();
  //
  //   var filtered = this.collection.filter(function (course) {
  //     var pattern = new RegExp(query, "gi");
  //     var result = course.get("name").match(pattern);
  //     return result
  //   })
  //
  //   var filteredCollection = new MerlinsBoard.Collections.Courses([],{owner: MerlinsBoard.CurrentUser});
  //   filteredCollection.set(filtered);
  //
  //   var searchList = new MerlinsBoard.Views.CoursesList({collection: filteredCollection});
	// 	this.$('section.course-results').html(searchList.render().$el);
	// }

	search: function (event) {
		event.preventDefault();
	  var queryCourse = $("input.course-find-input").val();
		this.searchCollection.fetch({data: $.param(query: queryCourse)});

		var searchList = new MerlinsBoard.Views.CoursesList({collection: this.searchCollection});
		$('section.course-results').html(searchList.render.$el); //needs to be global from DOM.
	}

})
