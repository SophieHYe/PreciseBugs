package controllers;

import models.User;
import play.mvc.Controller;
import play.mvc.Result;
import database.UsersDao;
import views.html.user_info;

public class UsersController extends Controller {

	public static Result invite(int requestedUserId) {
		try {
			int userId = Integer.parseInt(session("user_id"));
			UsersDao.get().inviteUser(userId, requestedUserId);
			return ok();
		} catch (Exception e) {
			return badRequest();
		}
	}

	public static Result decline(int requesingUserId) {
		try {
			int userId = Integer.parseInt(session("user_id"));
			UsersDao.get().removeRequest(userId, requesingUserId);
			return ok();
		} catch (Exception e) {
			return badRequest();
		}
	}
	
	public static Result showUser(int foreignerId) {
		int userId = Integer.parseInt(session("user_id"));
		User user = UsersDao.get().getById(foreignerId);
		boolean isYourFriend = UsersDao.get().areFriends(userId, foreignerId);
		if (user != null) {
			return ok(user_info.render(user, isYourFriend));			
		}
		else {
			return badRequest();
		}
	}
}
