package com.bijay.onlinevotingsystem.dao;

import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.util.ArrayList;
import java.util.List;

import com.bijay.onlinevotingsystem.dto.Admin;
import com.bijay.onlinevotingsystem.util.DbUtil;

public class AdminDaoImpl implements AdminDao {

	PreparedStatement ps = null;

	@Override
	public void saveAdminInfo(Admin admin) {
		String sql = "insert into admin_table(admin_name, password) values(?,?)";
		try {
			ps = DbUtil.getConnection().prepareStatement(sql);
			ps.setString(1, admin.getAdminName());
			ps.setString(2, admin.getPassword());
			ps.executeUpdate();
		} catch (ClassNotFoundException | SQLException e) {
			e.printStackTrace();
		}
	}

	@Override
	public List<Admin> getAllAdminInfo() {
		List<Admin> adminList = new ArrayList<>();
		String sql = "select * from admin_table";
		try {
			ps = DbUtil.getConnection().prepareStatement(sql);
			ResultSet rs = ps.executeQuery();
			while (rs.next()) {
				Admin admin = new Admin();
				admin.setId(rs.getInt("id"));
				admin.setAdminName(rs.getString("admin_name"));
				admin.setPassword(rs.getString("password"));
				adminList.add(admin);
			}
		} catch (ClassNotFoundException | SQLException e) {
			e.printStackTrace();
		}
		return adminList;
	}

	@Override
	public void deleteAdminInfo(int id) {

		String sql = "delete from admin_table where id=?";
		try {
			ps = DbUtil.getConnection().prepareStatement(sql);
			ps.setInt(1, id);
			ps.executeUpdate();
		} catch (ClassNotFoundException | SQLException e) {
			e.printStackTrace();
		}
	}

	@Override
	public Admin getAdminInfoById(int id) {

		Admin admin = new Admin();
		String sql = "select * from admin_table where id=?";
		try {
			ps = DbUtil.getConnection().prepareStatement(sql);
			ps.setInt(1, id);
			ResultSet rs = ps.executeQuery();
			if (rs.next()) {
				admin.setId(rs.getInt("id"));
				admin.setAdminName(rs.getString("admin_name"));
				admin.setPassword(rs.getString("password"));

			}
		} catch (ClassNotFoundException | SQLException e) {
			e.printStackTrace();
		}
		return admin;
	}

	@Override
	public void updateAdminInfo(Admin admin) {

		String sql = "update admin_table set admin_name=?, password=? where id=?";
		try {
			ps = DbUtil.getConnection().prepareStatement(sql);
			ps.setString(1, admin.getAdminName());
			ps.setString(2, admin.getPassword());
			ps.setInt(3, admin.getId());
			ps.executeUpdate();
		} catch (ClassNotFoundException | SQLException e) {
			e.printStackTrace();
		}
	}

	@Override
	public boolean loginValidate(String userName, String password) {

		String sql = "select * from admin_table where admin_name=? and password=?";
		try {
			ps=DbUtil.getConnection().prepareStatement(sql);
			ps.setString(1, userName);
			ps.setString(2,password);
			ResultSet rs =ps.executeQuery();
			if (rs.next()) {
				return true;
			}
		} catch (SQLException | ClassNotFoundException e) {
			e.printStackTrace();
		}
		return false;
	}
}