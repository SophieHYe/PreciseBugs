;(function (root, $) {

  var defaults = {
    users: 0
  };

  function RoomElement (options) {
    this.options = _.extend({}, defaults, options);
    this.prepare();
    this.bind();
  }

  RoomElement.prototype.prepare = function () {
    this.template = _.template($(this.options.template).html());
    this.$el = $('<li />');
    this.el = this.$el[0];
    this.render();
  };

  RoomElement.prototype.render = function () {
    // HTML escape the subject before it is posted to the main page
    this.options.subject = $('<div/>').text(this.options.subject).html();
    this.$el.html(this.template(this.options));

    this.$el.find('.tooltip-top').tooltipster({
      theme: 'tooltipster-light',
      position: 'top'
    });
  };

  RoomElement.prototype.bind = function () {
    this.options.developersReference.on('child_added', this.onUserAdd.bind(this));
    this.options.developersReference.on('child_removed', this.onUserRemoved.bind(this));

    this.options.watchersReference.on('child_added', this.onUserAdd.bind(this));
    this.options.watchersReference.on('child_removed', this.onUserRemoved.bind(this));

    this.$el.find('a').not('.watch').on('click', this.onClick.bind(this));
  };

  RoomElement.prototype.onUserAdd = function () {
    this.options.users++;
    this.render();
  };

  RoomElement.prototype.onUserRemoved = function () {
    this.options.users--;
    this.render();
  };

  RoomElement.prototype.onClick = function (event) {
    if (this.options.users >= this.options.userLimit) {
      alert('You cant enter into this room, too many users.');
      event.preventDefault();
    }
  };

  root.RoomElement = RoomElement;

} (window, jQuery));
