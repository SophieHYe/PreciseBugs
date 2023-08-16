'use strict';

// Declare app level module which depends on views, and components
var app = angular.module('myApp', [
  'ngRoute',
  'myApp.tests',
  'myApp.stats',
  'myApp.version'
]).
config(['$routeProvider', function($routeProvider) {
  $routeProvider.otherwise({redirectTo: '/tests'});
}]);

app.controller('projectCtrl', function($scope, $location, $http, myFactory) {
	$scope.project = {};
	
	$http.get('http://localhost:4968/getProjects').success(function(data, status, headers, config) {		
		$scope.projects = data;  
	}).error(function(data, status, headers, config) {
		var objs = [];
		objs[0] = {name : "Uh-oh - error fetching project", error : "Couldn't connect to local server on port 4968."};
		$scope.projects = objs;
	});
	
	$scope.init = function () {
		myFactory.set("tests_Default");
		$scope.project.name = "Default";
	};
	
	$scope.switchProject = function(project){
		myFactory.set(project);
		$scope.project.name = project.substring(6);
		$location.path('/app/index.html#/tests');
	};
});

app.factory('myFactory', function() {
	var savedData = {};
	function set(data) {
		savedData = data;
	}
	function get() {
		return savedData;
	}

	return {
		set: set,
		get: get
	}

});
