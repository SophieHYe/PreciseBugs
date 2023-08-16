package controllers;

import java.sql.Date;

import models.Secured;
import play.data.DynamicForm;
import play.data.Form;
import play.mvc.Controller;
import play.mvc.Result;
import play.mvc.Security;
import database.UsersDao;

@Security.Authenticated(Secured.class)
public class AccountController extends Controller {
	
	public static Result changePassword() {
		DynamicForm requestData = Form.form().bindFromRequest();
		String oldPassword = requestData.get("oldPassword");
		String newPassword = requestData.get("newPassword");
		String repeatedPassword = requestData.get("repeatedPassword");
		int userId;
		try {
			userId = Integer.parseInt(session(Application.USER_ID));
		} catch (Exception e) {
			e.printStackTrace();
			return badRequest();
		}
		
		if (newPassword.equals(repeatedPassword)) {
			if (UsersDao.get().checkPasswordForUser(userId, oldPassword)) {
				UsersDao.get().changePassword(userId, newPassword);
				return ok();
			}
			else {
				return badRequest("Old password is incorrect!");
			}
		}
		else {
			return badRequest("New and repeated passwords are different!");
		}
	}
	
	public static Result changeUserInfo() {
		DynamicForm requestData = Form.form().bindFromRequest();
		Double weight = null;
		Double height = null;
		Date dateOfBirth = null;
		int userId;
		try {
			userId = Integer.parseInt(session(Application.USER_ID));
		} catch (Exception e) {
			e.printStackTrace();
			return badRequest();
		}

		if (!requestData.get("weight").equals(""))
			weight = Double.valueOf(requestData.get("weight"));
		if (!requestData.get("height").equals(""))
			height = Double.valueOf(requestData.get("height"));
		if (!requestData.get("dateOfBirth").equals(""))
			dateOfBirth = Date.valueOf(requestData.get("dateOfBirth"));

		try {
			if (UsersDao.get().update(userId, weight, height, dateOfBirth))
				return ok();
			else
				return badRequest();
		} catch (Exception e) {
			e.printStackTrace();
			return badRequest();
		}
	}
}
