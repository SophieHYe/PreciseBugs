package business.impl;

import business.ReviewService;
import com.avaje.ebean.Expr;
import models.Review;
import play.db.ebean.Model;

import java.util.List;

/**
 * Created by Dani on 13/3/15.
 */
public class ReviewServiceImpl implements ReviewService {

    private static Model.Finder<Integer, Review> find = new Model.Finder<>(
            Integer.class, Review.class
    );

    @Override
    public List<Review> getByMovieId(int movieId) {
        return find.where().eq("movieId", movieId).findList();
    }

    @Override
    public Review getByMovieIdAndUsername(int movieId, String username) {
        return find.where().and(Expr.eq("movieId", movieId), Expr.eq("username", username)).findUnique();
    }

    @Override
    public Review rateMovie(String username, int movieId, String comment, double rating) {
        Review review = new Review(username, movieId, comment, rating);
        review.save();
        return review;
    }
}
