MerlinsBoard.Views.CoursesSearch = Backbone.View.extend({
	initialize: function () {
	},

	template: JST["courses/coursesearch"],

	render: function () {
		var renderedContent = this.template({courses: this.collection});
		this.$el.html(renderedContent);
		return this
	},

	searchCollection: function () {
		if (!this._searchCollection) {
			this._searchCollection = new MerlinsBoard.Collections.CoursesSearch();
		}

		return this._searchCollection
	},

  tagName: "section",

  className: "course-search",

	events: {
		"submit form.course-find":"search"
	},

	search: function (event) {
		event.preventDefault();
	  var queryCourse = $("input.course-find-input").val();
		this.searchCollection().fetch({data: $.param({query: queryCourse})});

		var searchList = new MerlinsBoard.Views.CoursesList({collection: this.searchCollection()});
		//want to call remove on search results
		$('section.course-results').html(searchList.render().$el);
	}

})
