MerlinsBoard.Views.CoursesList = Backbone.View.extend({
	initialize: function () {
 		this.listenTo(this.collection, "reset add sync", this.render);
	},

	template: JST["courses/list"],

	tagName: "ul",

	className: "course-list",

	render: function () {
		var renderedContent = this.template({courses: this.collection});
		this.$el.html(renderedContent);
		return this
	}

})
