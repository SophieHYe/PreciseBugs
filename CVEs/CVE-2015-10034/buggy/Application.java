package controllers;

import java.util.List;

import models.News;
import models.Secured;
import models.Stranger;
import models.User;
import play.mvc.Controller;
import play.mvc.Result;
import play.mvc.Security;
import views.html.account;
import views.html.index;
import database.NewsDao;
import database.UsersDao;

@Security.Authenticated(Secured.class)
public class Application extends Controller {
	
	public static final String USER_ID = "user_id";
	
	public static final String HOME = "/home";
	public static final String LOGIN = "/login";

    public static Result home() {
    	String userId = session(USER_ID);
    	if (userId == null) {
    		return redirect(LOGIN);
    	}
    	else {
    		List<Stranger> strangers = UsersDao.get().getStrangersForUser(Integer.parseInt(userId));
    		List<User> friendshipRequests = UsersDao.get().getFriendshipRequests(Integer.parseInt(userId));
    		List<News> news = NewsDao.get().getNews(Integer.parseInt(userId));
    		return ok(index.render(news, strangers, friendshipRequests));
    	}
    }
    
    public static Result editAccountSettings() {
    	User user = UsersDao.get().getById(session(USER_ID)); //TODO change session to userId
    	return ok(account.render(user));
    }

}
