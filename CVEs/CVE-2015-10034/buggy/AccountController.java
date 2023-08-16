package controllers;

import java.util.HashMap;
import java.util.Map;

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
		
		if (newPassword.equals(repeatedPassword)) {
			if (UsersDao.get().checkPasswordForUser(session().get(Application.USER_ID), oldPassword)) {
				UsersDao.get().changePassword(session().get(Application.USER_ID), newPassword);
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
		String userId = session(Application.USER_ID);
		DynamicForm requestData = Form.form().bindFromRequest();
		String weight = requestData.get("weight");
		String height = requestData.get("height");
		String dateOfBirth = requestData.get("dateOfBirth");

		Map<String, String> toUpdate = new HashMap<String, String>();
		if (!weight.equals("")) {
			toUpdate.put("weight", weight);
		}
		if (!height.equals("")) {
			toUpdate.put("height", height);
		}
		if (!dateOfBirth.equals("")) {
			toUpdate.put("date_of_birth", dateOfBirth);
		}
		

		if (toUpdate.size() > 0 && UsersDao.get().update(userId, toUpdate)) {
			return ok();
		}
		else {
			return badRequest();
		}
	}
}
