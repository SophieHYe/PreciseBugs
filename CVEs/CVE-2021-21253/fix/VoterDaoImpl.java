package com.bijay.onlinevotingsystem.dao;

import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.util.ArrayList;
import java.util.List;
import java.sql.Date;

import com.bijay.onlinevotingsystem.controller.SHA256;
import com.bijay.onlinevotingsystem.dto.Voter;
import com.bijay.onlinevotingsystem.util.DbUtil;

public class VoterDaoImpl implements VoterDao {

	PreparedStatement ps = null;

	@Override
	public void saveVoterInfo(Voter voter) {
		String sql = "insert into voter_table(voter_name, password, gender, state_no, district, email, dob, imageurl) values(?,?,?,?,?,?,?,?)";
		try {
			ps = DbUtil.getConnection().prepareStatement(sql);
			ps.setString(1, voter.getVoterName());
			ps.setString(2, voter.getPassword());
			ps.setString(3, voter.getGender());
			ps.setInt(4, voter.getStateNo());
			ps.setString(5, voter.getDistrictName());
			ps.setString(6, voter.getEmail());
			ps.setDate(7, new Date(voter.getDob().getTime()));
			ps.setString(8, voter.getImgUrl());
			ps.executeUpdate();
		} catch (ClassNotFoundException | SQLException e) {
			e.printStackTrace();
		}
	}

	@Override
	public List<Voter> getAllVoterInfo() {
		List<Voter> voterList = new ArrayList<>();
		String sql = "select * from voter_table";
		try {
			ps = DbUtil.getConnection().prepareStatement(sql);
			ResultSet rs = ps.executeQuery();
			while (rs.next()) {
				Voter voter = new Voter();
				voter.setId(rs.getInt("id"));
				voter.setVoterName(rs.getString("voter_name"));
				voter.setPassword(rs.getString("password"));
				voter.setStateNo(rs.getInt("state_no"));
				voter.setDistrictName(rs.getString("district"));
				voter.setGender(rs.getString("gender"));
				voter.setImgUrl(rs.getString("imageurl"));
				voter.setEmail(rs.getString("email"));
				voter.setDob(rs.getDate("dob"));
				voterList.add(voter);
			}
		} catch (ClassNotFoundException | SQLException e) {
			e.printStackTrace();
		}
		return voterList;
	}

	@Override
	public void updateVoterInfo(Voter voter) {
		// TODO Auto-generated method stub

	}

	@Override
	public void deleteVoterInfo(int id) {

		String sql = "delete from voter_table where id=?";
		try {
			ps = DbUtil.getConnection().prepareStatement(sql);
			ps.setInt(1, id);
			ps.executeUpdate();
		} catch (ClassNotFoundException | SQLException e) {
			e.printStackTrace();
		}
	}

	@Override
	public boolean loginValidate(String userName, String password, String email) {
		String sql = "select * from voter_table where voter_name=? and email=?";
		try {
			ps = DbUtil.getConnection().prepareStatement(sql);
			ps.setString(1, userName);
			ps.setString(2, email);
			ResultSet rs = ps.executeQuery();
			if (rs.next()) {
				String cipherText = rs.getString("password");
				return SHA256.validatePassword(password, cipherText);
			}
		} catch (ClassNotFoundException | SQLException e) {
			e.printStackTrace();
		}
		return false;
	}
}