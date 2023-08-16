package com.bijay.onlinevotingsystem.controller;

import java.io.IOException;

import javax.servlet.RequestDispatcher;
import javax.servlet.ServletException;
import javax.servlet.annotation.WebServlet;
import javax.servlet.http.Cookie;
import javax.servlet.http.HttpServlet;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import javax.servlet.http.HttpSession;

import com.bijay.onlinevotingsystem.dao.AdminDao;
import com.bijay.onlinevotingsystem.dao.AdminDaoImpl;

@WebServlet("/aLoginController")
public class AdminLoginController extends HttpServlet {
	private static final long serialVersionUID = 1L;
	AdminDao adminDao = new AdminDaoImpl();

	protected void doGet(HttpServletRequest request, HttpServletResponse response)
			throws ServletException, IOException {

		HttpSession session = request.getSession();
		session.invalidate();

		RequestDispatcher rd = request.getRequestDispatcher("adminlogin.jsp");
		request.setAttribute("loggedOutMsg", "Log Out Successful");
		rd.include(request, response);
	}

	protected void doPost(HttpServletRequest request, HttpServletResponse response)
			throws ServletException, IOException {

		// to get values from the login page
		String userName = request.getParameter("aname");
		String password = request.getParameter("pass");
		// String password = request.getParameter("pass");
		String rememberMe = request.getParameter("remember-me");

		// validation

		if (adminDao.loginValidate(userName, password)) {

			if (rememberMe != null) {
				Cookie cookie1 = new Cookie("uname", userName);
				Cookie cookie2 = new Cookie("pass", password);

				cookie1.setMaxAge(24 * 60 * 60);
				cookie2.setMaxAge(24 * 60 * 60);

				response.addCookie(cookie1);
				response.addCookie(cookie2);
			}

			// to display the name of logged-in person in home page
			HttpSession session = request.getSession();
			session.setAttribute("username", userName);

			/*
			 * RequestDispatcher rd =
			 * request.getRequestDispatcher("AdminController?actions=admin_list");
			 * rd.forward(request, response);
			 */

			response.sendRedirect("AdminController?actions=admin_list");
		} else {
			RequestDispatcher rd = request.getRequestDispatcher("adminlogin.jsp");
			request.setAttribute("loginFailMsg", "Invalid Username or Password !!");
			rd.include(request, response);
		}
	}
}
