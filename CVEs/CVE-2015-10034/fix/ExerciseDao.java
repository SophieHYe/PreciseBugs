package database;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.sql.Statement;
import java.util.ArrayList;
import java.util.HashSet;
import java.util.List;
import java.util.Set;

import models.Exercise;
import models.ExerciseResult;
import models.MuscleGroup;
import play.db.DB;

public class ExerciseDao {
	
	public static ExerciseDao get() {
		return INSTANCE;
	}
	
	public List<Exercise> getAll() {
		List<Exercise> exercises = new ArrayList<Exercise>();
		
		Connection connection = null;
		try {
			connection = DB.getConnection();
			Statement statement = connection.createStatement();
			ResultSet exerciseSet = statement.executeQuery(
					"SELECT exercises.*, rating, ratings_count, "
					+ " ARRAY_AGG(muscle_name) AS target_muscles "
					+ "FROM exercises "
					+ "LEFT JOIN ("
					+ "	SELECT exercise_id, AVG(rating) AS rating,"
					+ "  COUNT(rating) AS ratings_count"
					+ "	FROM exercise_ratings"
					+ " GROUP BY exercise_id"
					+ ") exercise_ratings USING (exercise_id) "
					+ "LEFT JOIN target_muscles USING (exercise_id) "
					+ "GROUP BY exercises.exercise_id, rating, ratings_count "
					+ "ORDER BY exercises.exercise_name");
			
			while (exerciseSet.next()) {
				Exercise e = new Exercise();
				e.setId(exerciseSet.getInt("exercise_id"));
				e.setName(exerciseSet.getString("exercise_name"));
				e.setDescription(exerciseSet.getString("description"));
				e.setMovieUri(exerciseSet.getString("movie_uri"));
				e.setRating(exerciseSet.getDouble("rating"));
				e.setRatingsCount(exerciseSet.getInt("ratings_count"));

				ResultSet targetMuscleSet = exerciseSet.getArray("target_muscles").getResultSet();
				Set<MuscleGroup> targetMuscles = new HashSet<MuscleGroup>();

				while (targetMuscleSet.next()) {
					MuscleGroup muscleGroup = new MuscleGroup();
					muscleGroup.setMuscleName(targetMuscleSet.getString(2));

					targetMuscles.add(muscleGroup);
				}

				targetMuscleSet.close();
				e.setTargetMuscles(targetMuscles);

				exercises.add(e);
			}
			
			exerciseSet.close();
			statement.close();
		} catch (SQLException e) {
			e.printStackTrace();
		} finally {
			if (connection != null) {
				try {
					connection.close();
				} catch (SQLException e) {
					e.printStackTrace();
				}
			}
		}

		return exercises;
	}
	
	public List<Exercise> filter(String muscleName) {
		MuscleGroup muscle = new MuscleGroup(muscleName);
		List<Exercise> result = new ArrayList<Exercise>();
		for (Exercise ex : getAll()) {
			if (ex.getTargetMuscles().contains(muscle)) {
				result.add(ex);
			}
		}		
		return result;
	}
	
	public void rateExercise(int userId, int exerciseId, int rating) {
		Connection connection = null;
		try {
			connection = DB.getConnection();
			PreparedStatement p = connection.prepareStatement("INSERT INTO exercise_ratings(user_id, exercise_id, rating) VALUES (?, ?, ?);");
			p.setInt(1, userId);
			p.setInt(2, exerciseId);
			p.setInt(3, rating);
			
			p.executeQuery();
			p.close();
		} catch (SQLException e) {
			e.printStackTrace();
		} finally {
			if (connection != null) {
				try {
					connection.close();
				} catch (SQLException e) {
					e.printStackTrace();
				}
			}
		}
	}

	private static final ExerciseDao INSTANCE = new ExerciseDao();
	
	private ExerciseDao() {}

	public ExerciseResult getBestForUser(String userId, int exerciseId) {
		Connection connection = null;
		try {
			connection = DB.getConnection();
			PreparedStatement p = connection.prepareStatement("SELECT weight, set_count, reps_per_set"
					+ "FROM workout_entries "
					+ "JOIN workouts USING (workout_id) "
					+ "WHERE user_id = ? AND exercise_id = ? "
					+ "ORDER BY weight"
					+ "LIMIT 1;");
			ResultSet resultSet = p.executeQuery();
			ExerciseResult result = null;
			if (resultSet.next()) {
				result = new ExerciseResult();
				result.setRepsPerSet(resultSet.getInt("reps_per_set"));
				result.setSetCount(resultSet.getInt("set_count"));
				result.setWeight(resultSet.getInt("weight"));
				
			}
			resultSet.close();
			p.close();
			return result;
		} catch (SQLException e) {
			e.printStackTrace();
			return null;
		} finally {
			if (connection != null) {
				try {
					connection.close();
				} catch (SQLException e) {
					e.printStackTrace();
				}
			}
		}
	}
	
}
