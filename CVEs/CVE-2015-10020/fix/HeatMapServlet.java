package com.datformers.servlet;

import java.io.IOException;
import java.sql.ResultSet;
import java.sql.SQLException;

import javax.servlet.http.HttpServlet;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;

import org.json.simple.JSONArray;
import org.json.simple.JSONObject;

import com.datformers.database.OracleDBWrapper;
import com.datformers.utils.DatabaseUtil;

public class HeatMapServlet extends HttpServlet {
	private OracleDBWrapper dbWrapper = new OracleDBWrapper(DatabaseUtil.getURL(DatabaseUtil.IP), DatabaseUtil.UERNAME,DatabaseUtil.PASSWORD);	
	@Override
	public void doGet(HttpServletRequest request, HttpServletResponse response) {
		//System.out.println("SERVLET GOT CALL"+request.getPathInfo());
		
		String keys[] = {"latitude","longitude","name","check_in_info","points1"};
		if(request.getPathInfo()!=null && request.getPathInfo().contains("points1")) {
			//Connect to database
			String city = request.getParameter("city");
			String category = request.getParameter("category");
			//System.out.println("Got request for: "+city+" and category: "+category);
			//System.out.println(city + ":");
			String queryString = "select business.latitude,business.longitude,business.name,c.check_in_info "
					+ "from business "
					+ "inner join categories "
					+ "on categories.bid = business.bid "
					+ "inner join checkin c "
					+ "on c.bid = business.bid "
					+ "where business.city='" + city 
					+ "' and categories.category='"+category +"'"
					+"order by c.check_in_info DESC";
			
			ResultSet set = dbWrapper.executeQuery(queryString);
			if(set==null) {
				return;
			}
			
			//Create response
			JSONArray array = new JSONArray();
			int count = 0;
			try {
				while(set.next()) {
					count++;
					JSONObject obj = new JSONObject();
					obj.put(keys[0], set.getDouble(keys[0]));
					obj.put(keys[1], set.getDouble(keys[1]));
					obj.put(keys[3],  set.getInt(keys[3]));
					obj.put(keys[2],  set.getString(keys[2]));
							
					array.add(obj);
				}
				JSONObject resultObject = new JSONObject();
				resultObject.put("source", "We got from database");
				resultObject.put("city", city);
				resultObject.put("category", category);
				resultObject.put("points", array);
				response.setContentType("application/json");
				//System.out.println("JSON Object" + resultObject);
				response.getWriter().println(resultObject.toString());
			} catch (SQLException | IOException e) {
				e.printStackTrace();
				System.out.println("Heat map Servlet: "+e.getMessage());
			}
		} else if(request.getPathInfo()!=null && request.getPathInfo().contains("points")) {
			double latitude[] = {40.35762,40.391255,40.4704573375885,40.4285874,40.492067,40.499398,40.487671,40.495483,40.459082,40.4499962,40.463717,40.4882149,40.450249,40.467794,40.462105,40.457881,40.458577,40.458335,40.4852511,40.4582511,40.4653904,40.458703,40.4657572,40.457598,40.457271,40.4606263,40.464176,40.485645,40.474884,40.459747,40.4583902,40.4579265,40.4575612,40.4582381,40.458477,40.4638365,40.4584723,40.4592437,40.4617424,40.4605003,40.475405,40.4601614,40.4613612,40.4605159,40.464615,40.463935,40.457589,40.4641113,40.4582251,40.4573589,40.4585106474725,40.460997,40.459676,40.458522,40.4572607,40.4538201,40.458138,40.464485,40.4572935,40.4502135,40.4472949,40.4481991,40.4496169180659,40.447942,40.447942,40.4574605383472,40.457835,40.4484869,40.4148896,40.4407497519697,40.4115087,40.415053,40.4147064,40.4216159,40.4218925,40.3668,40.3926169,40.3923844,40.393115,40.391732,40.388311,40.39261,40.3923831,40.387167,40.4531335,40.4534167,40.455167,40.4541341,40.4535039,40.4521309,40.4959456,40.4489884,40.455833,40.4621686,40.455167,40.4643755,40.4457075,40.4435041,40.4546428,40.4566455,40.4566092,40.4566994,40.4509987,40.4542747,40.454859,40.4534984,40.4543344,40.455929,40.4531769,40.4480678,40.4527029402106,40.4535915,40.456259,40.4460204,40.4534946,40.4481509,40.4480946,40.464958,40.4484039,40.4730851446399,40.4846476,40.4702876,40.4812123,40.4851129,40.4810824,40.489973,40.4215177,40.3918758,40.3959923,40.3950481,40.3943248,40.3949331,40.3953119,40.409199,40.3996765,40.3992789,40.3971675,40.409125,40.394911,40.388562,40.38892,40.3953565,40.3942631,40.3954709,40.420335,40.412659,40.4025781,40.3885545,40.401054,40.395871,40.3958828,40.443213,40.390935,40.422068,40.3897336,40.397846,40.3959206,40.3966306,40.396132,40.388395,40.420335,40.420335,40.414716,40.409944,40.3886105,40.3886666};
			double longitude[] = {-80.05998,-80.073426,-80.0889587402344,-79.9865459,-80.06235,-80.07249,-80.055464,-80.065652,-80.077006,-80.0575565,-79.931884,-79.920642,-79.9145,-79.926611,-79.922481,-79.924269,-79.921053,-79.924717,-79.9259794,-79.9251169,-79.9229971,-79.922447,-79.9148356,-79.9100009,-79.925517,-79.9238313,-79.9252199,-79.926487,-79.918862,-79.9185679,-79.9255146,-79.9180322,-79.9250618,-79.9251176,-79.921901,-79.9316767,-79.9187095,-79.9313671,-79.9251969,-79.9256896,-79.919627,-79.9274944,-79.9259052,-79.925241,-79.922397,-79.933126,-79.9254969,-79.9250911,-79.9251182,-79.9251969,-79.928830198608,-79.928355,-79.923615,-79.925436,-79.9336632,-79.9211464,-79.924797,-79.935388,-79.9252056,-79.9143225,-79.90508,-79.8954753,-79.898855609787,-79.895957,-79.895957,-79.9084721005249,-79.9275276,-79.9012971,-79.987783,-79.9996304512024,-79.9558863,-79.987206,-79.9877685,-79.9934656,-79.992815,-79.981359,-79.9866014,-79.9866275,-79.986911,-79.98718,-79.996225,-79.9968169,-79.9972486,-79.98224,-80.0013672,-79.9997426,-79.999815,-79.9981189,-79.9992711,-79.9981452,-79.9606482,-80.0088714,-80.006512,-80.0190648,-79.994957,-79.9821671,-80.018213,-79.9495881,-79.9907425,-80.0070383,-80.0015054,-80.0105349,-80.0009426,-80.0005142,-79.999162,-80.0012052,-80.0126074,-80.0065429,-80.001199,-80.0042395,-80.0066578388214,-80.0008071,-80.014446,-80.0153356,-79.9993338,-80.0040779,-80.0042488,-79.983936,-80.010841,-79.9621242834648,-80.0354937,-80.0302515,-80.0414427,-80.0416964,-80.0414027,-80.018177,-80.0297492,-80.037826,-80.0350352,-80.034704,-80.0464255,-80.0342686,-80.0343,-80.031761,-80.0434133,-80.0445647,-80.0294805,-80.024957,-80.0339862,-80.049448,-80.049921,-80.0345551,-80.0352133,-80.0347424,-80.030114,-80.030322,-80.041926,-80.0500275,-80.0433063,-80.033585,-80.0466445,-79.9534393,-80.039142,-80.029239,-80.0409102,-80.036197,-80.0348023,-80.0357966,-80.033232,-80.049821,-80.030114,-80.030114,-80.03063,-80.024985,-80.0499034,-80.049779};
			String city = request.getParameter("city");
			String category = request.getParameter("category");
			//System.out.println("Got request for: "+city);
			JSONArray array = new JSONArray();
			for(int i=0; i<latitude.length; i++) {
				JSONObject obj = new JSONObject();
				obj.put(keys[0], latitude[i]);
				obj.put(keys[1], longitude[i]);
				array.add(obj);
			}
			JSONObject resultObject = new JSONObject();
			resultObject.put("city", city);
			resultObject.put("points", array);
			resultObject.put("category", category);
			response.setContentType("application/json");
			try {
				response.getWriter().println(resultObject.toString());
			} catch (IOException e) {
				e.printStackTrace();
			}
		} else {
			try {
				response.setContentType("text/plain");
				response.getWriter().println("This is the response");
			} catch (IOException e) {
				// TODO Auto-generated catch block
				e.printStackTrace();
			}
		}
	}
	@Override
	public void destroy() {
		dbWrapper.deregister();
	}
	@Override 
	public void doPost(HttpServletRequest request, HttpServletResponse response) {
		
	}

}
