function checkusername() {
	var username=$('#newuser input[name=user]').val();
	var password=$('#newuser input[name=password]').val();
		if(username=="" || password=="") {
			toastr.error('You must enter a username and a password');
				if(username=="") {
					newuser.user.focus();
				} else if(password=="") {
					newuser.password.focus();
				}
		} else if(alphanumeric(username)==false || alphanumeric(password)==false) {
			if(alphanumeric(username)==false) {
				toastr.error('Username contains invalid characters');
				newuser.user.focus();
			} else if(alphanumeric(password)==false) {
				toastr.error('Password contains invalid characters');
				newuser.password.focus();
			}
		} else {
			jQuery.ajax({
				type: 'post',
				url: 'functions/ajaxhelper.php',
				data: 'function=1&username='+username,
				cache: false,
				success: function(response) {
					if(response==1) {
						toastr.error('Username already exists');
						newuser.user.focus();
					} else {
						$('#newuser').trigger('submit', true);
					}
				}
			});
		}
}

function checkeditusername() {
	var username=$('#edituser input[name=user]').val();
	var rusername=$('#edituser input[name=ruser]').val();
	var password=$('#edituser input[name=password]').val();
		if(username=="" || password=="") {
			toastr.error('You must enter a username and a password');
				if(password=="") {
					edituser.password.focus();
				}
				if(username=="") {
					edituser.user.focus();
				}
		} else if(alphanumeric(username)==false || alphanumeric(password)==false) {
			if(alphanumeric(username)==false) {
				toastr.error('Username contains invalid characters');
				newuser.user.focus();
			} else if(alphanumeric(password)==false) {
				toastr.error('Password contains invalid characters');
				newuser.password.focus();
			}
		} else {
			if(username!=rusername) {
				jQuery.ajax({
					type: 'post',
					url: 'functions/ajaxhelper.php',
					data: 'function=1&username='+username,
					cache: false,
					success: function(response) {
						if(response==1) {
							toastr.error('Username already exists');
							edituser.user.focus();
						} else {
							$('#edituser').trigger('submit', true);
						}
					}
				});
			} else {
				$('#edituser').trigger('submit', true);
			}
		}
}

function checkgroupname() {
	var groupname=$('#newgroup input[name=name]').val();
		if(groupname=="") {
			toastr.error('You must enter a group name');
			newgroup.name.focus();
		} else {
			jQuery.ajax({
				type: 'post',
				url: 'functions/ajaxhelper.php',
				data: 'function=2&groupname='+groupname,
				cache: false,
				success: function(response) {
					if(response==1) {
						toastr.error('Group already exists');
						newgroup.name.focus();
					} else {
						$('#newgroup').trigger('submit', true);
					}
				}
			});
		}
}
		
function checkeditgroupname() {
	var groupname=$('#editgroup input[name=name]').val();
	var rgroupname=$('#editgroup input[name=rname]').val();
		if(groupname=="") {
			toastr.error('You must enter a group name');
			editgroup.name.focus();
		} else {
			if(groupname!=rgroupname) {
				jQuery.ajax({
					type: 'post',
					url: 'functions/ajaxhelper.php',
					data: 'function=2&groupname='+groupname,
					cache: false,
					success: function(response) {
						if(response==1) {
							toastr.error('Group already exists');
							editgroup.name.focus();
						} else {
							$('#editgroup').trigger('submit', true);
						}
					}
				});
			} else {
				$('#editgroup').trigger('submit', true);
			}
		}
}

function checkprofilename() {
	var profilename=$('#newprofile input[name=name]').val();
	var cspvalue=$('#newprofile input[name=cspvalue]').val();
		if(profilename=="") {
			toastr.error('You must enter a profile name');
			newprofile.name.focus();
		} else {
			jQuery.ajax({
				type: 'post',
				url: 'functions/ajaxhelper.php',
				data: 'function=3&profilename='+profilename,
				cache: false,
				success: function(response) {
					if(response==1) {
						toastr.error('Profile already exists');
						newprofile.name.focus();
					} else {
						jQuery.ajax({
							type: 'post',
							url: 'functions/ajaxhelper.php',
							data: 'function=5&cspvalue='+cspvalue,
							cache: false,
							success: function(response) {
								if(response==1) {
									toastr.error('CSP value already exists');
									newprofile.cspvalue.focus();
								} else {
									$('#newprofile').trigger('submit', true);
								}
							}
						});
					}
				}
			});
		}
}

function checkeditprofilename() {
	var profilename=$('#editprofile input[name=name]').val();
	var rprofilename=$('#editprofile input[name=rname]').val();
	var cspvalue=$('#editprofile input[name=cspvalue]').val();
	var rcspvalue=$('#editprofile input[name=rcspvalue]').val();
		if(profilename=="") {
			toastr.error('You must enter a profile name');
			editprofile.name.focus();
		} else {
			if(profilename!=rprofilename) {
				jQuery.ajax({
					type: 'post',
					url: 'functions/ajaxhelper.php',
					data: 'function=3&profilename='+profilename,
					cache: false,
					success: function(response) {
						if(response==1) {
							toastr.error('Profile already exists');
							editprofile.name.focus();
						} else {
							if(cspvalue!=rcspvalue) {
								jQuery.ajax({
									type: 'post',
									url: 'functions/ajaxhelper.php',
									data: 'function=5&cspvalue='+cspvalue,
									cache: false,
									success: function(response) {
										if(response==1) {
											toastr.error('CSP value already exists');
											editprofile.cspvalue.focus();
										} else {
											$('#editprofile').trigger('submit', true);
										}
									}
								});
							} else {
								$('#editprofile').trigger('submit', true);
							}
						}
					}
				});
			} else {
				if(cspvalue!=rcspvalue) {
					jQuery.ajax({
						type: 'post',
						url: 'functions/ajaxhelper.php',
						data: 'function=5&cspvalue='+cspvalue,
						cache: false,
						success: function(response) {
							if(response==1) {
								toastr.error('CSP value already exists');
								editprofile.cspvalue.focus();
							} else {
								$('#editprofile').trigger('submit', true);
							}
						}
					});
				} else {
					$('#editprofile').trigger('submit', true);
				}
			}
		}
}

function checkadminname() {
	var adminname=$('#newadmin input[name=user]').val();
	var adminpass=$('#newadmin input[name=pass]').val();
	var adminlevel=$('#newadmin select[name=admlvl]').val();
	var usergroup=$('#newadmin select[name=ugroup]').val();
		if(adminname=="" || adminpass=="") {
			toastr.error('You must enter a username and a password');
				if(adminname=="") {
					newadmin.user.focus();
				} else if(adminpass=="") {
					newadmin.pass.focus();
				}
		} else {
			jQuery.ajax({
				type: 'post',
				url: 'functions/ajaxhelper.php',
				data: 'function=4&adminname='+adminname,
				cache: false,
				success: function(response) {
					if(response==1) {
						toastr.error('Admin already exists');
						newadmin.user.focus();
					} else {
						if(adminlevel=="2" && usergroup=="0" || adminlevel=="2" && usergroup=="") {
							toastr.error('You must select a group');
							newadmin.ugroup.focus();
						} else {
							$('#newadmin').trigger('submit', true);
						}
					}
				}
			});
		}
}

function checkeditadminname() {
	var adminname=$('#editadmin input[name=user]').val();
	var radminname=$('#editadmin input[name=ruser]').val();
		if(adminname=="") {
			toastr.error('You must enter a username');
				if(adminname=="") {
					editadmin.user.focus();
				}
		} else {
			if(adminname!=radminname) {
				jQuery.ajax({
					type: 'post',
					url: 'functions/ajaxhelper.php',
					data: 'function=4&adminname='+adminname,
					cache: false,
					success: function(response) {
						if(response==1) {
							toastr.error('Admin already exists');
							editadmin.user.focus();
						} else {
							$('#editadmin').trigger('submit', true);
						}
					}
				});
			} else {
				$('#editadmin').trigger('submit', true);
			}
		}
}

function checkchpassadminname() {
	var adminpass1=$('#chpassadmin input[name=pass1]').val();
	var adminpass2=$('#chpassadmin input[name=pass2]').val();
		if(adminpass1=="" || adminpass2=="") {
			toastr.error('You must fill in both fields');
				if(adminpass1=="") {
					chpassadmin.pass1.focus();
				}
				if(adminpass2=="") {
					chpassadmin.pass2.focus();
				}
		} else {
			if(adminpass1==adminpass2) {
				$('#chpassadmin').trigger('submit', true);
			} else {
				toastr.error('Passwords dont match');
				chpassadmin.pass1.focus();
			}
		}
}

function enableuser(uid,admlvl,admgrp,admid) {
	if(uid!="") {
		jQuery.ajax({
			type: 'post',
			url: 'functions/ajaxhelper.php',
			data: 'function=6&uid='+uid+'&admlvl='+admlvl+'&admgrp='+admgrp+'&admid='+admid,
			cache: false,
			success: function(response) {
				if(response==0) {
					$('#usrenabled-'+uid).html('<span class=\"label label-success\">Enabled</span>');
					$('#usrlnkenabled-'+uid).attr('onclick','disableuser(\''+uid+'\',\''+admlvl+'\',\''+admgrp+'\',\''+admid+'\');');
					$('#ausrenabled-'+uid).html('Disable');
					$('#ausrenabled-'+uid).attr('onclick','disableuser(\''+uid+'\',\''+admlvl+'\',\''+admgrp+'\',\''+admid+'\');');
				}
				if(response==1) {
					toastr.error('This user does not belong to you');
				}
			}
		});
	}
}

function disableuser(uid,admlvl,admgrp,admid) {
	if(uid!="") {
		jQuery.ajax({
			type: 'post',
			url: 'functions/ajaxhelper.php',
			data: 'function=7&uid='+uid+'&admlvl='+admlvl+'&admgrp='+admgrp+'&admid='+admid,
			cache: false,
			success: function(response) {
				if(response==0) {
					$('#usrenabled-'+uid).html('<span class=\"label label-important\">Disabled</span>');
					$('#usrlnkenabled-'+uid).attr('onclick','enableuser(\''+uid+'\',\''+admlvl+'\',\''+admgrp+'\',\''+admid+'\');');
					$('#ausrenabled-'+uid).html('Enable');
					$('#ausrenabled-'+uid).attr('onclick','enableuser(\''+uid+'\',\''+admlvl+'\',\''+admgrp+'\',\''+admid+'\');');
				}
				if(response==1) {
					toastr.error('This user does not belong to you');
				}
			}
		});
	}
}

function enableadmin(aid) {
	if(aid!="") {
		jQuery.ajax({
			type: 'post',
			url: 'functions/ajaxhelper.php',
			data: 'function=8&aid='+aid,
			cache: false,
			success: function(response) {
				if(response==0) {
					$('#admenabled-'+aid).html('<span class=\"label label-success\">Enabled</span>');
					$('#admlnkenabled-'+aid).attr('onclick','disableadmin(\''+aid+'\');');
					$('#aadmenabled-'+aid).html('Disable');
					$('#aadmenabled-'+aid).attr('onclick','disableadmin(\''+aid+'\');');
				}
			}
		});
	}
}

function disableadmin(aid) {
	if(aid!="") {
		jQuery.ajax({
			type: 'post',
			url: 'functions/ajaxhelper.php',
			data: 'function=9&aid='+aid,
			cache: false,
			success: function(response) {
				if(response==0) {
					$('#admenabled-'+aid).html('<span class=\"label label-important\">Disabled</span>');
					$('#admlnkenabled-'+aid).attr('onclick','enableadmin(\''+aid+'\');');
					$('#aadmenabled-'+aid).html('Enable');
					$('#aadmenabled-'+aid).attr('onclick','enableadmin(\''+aid+'\');');
				}
			}
		});
	}
}

function enablegroup(gid) {
	if(gid!="") {
		jQuery.ajax({
			type: 'post',
			url: 'functions/ajaxhelper.php',
			data: 'function=15&gid='+gid,
			cache: false,
			success: function(response) {
				if(response==0) {
					$('#grpenabled-'+gid).html('<span class=\"label label-success\">Enabled</span>');
					$('#grplnkenabled-'+gid).attr('onclick','disablegroup(\''+gid+'\');');
					$('#agrpenabled-'+gid).html('Disable');
					$('#agrpenabled-'+gid).attr('onclick','disablegroup(\''+gid+'\');');
				}
			}
		});
	}
}

function disablegroup(gid) {
	if(gid!="") {
		jQuery.ajax({
			type: 'post',
			url: 'functions/ajaxhelper.php',
			data: 'function=16&gid='+gid,
			cache: false,
			success: function(response) {
				if(response==0) {
					$('#grpenabled-'+gid).html('<span class=\"label label-important\">Disabled</span>');
					$('#grplnkenabled-'+gid).attr('onclick','enablegroup(\''+gid+'\');');
					$('#agrpenabled-'+gid).html('Enable');
					$('#agrpenabled-'+gid).attr('onclick','enablegroup(\''+gid+'\');');
				}
			}
		});
	}
}

function checkquickedit() {
	var username=$('#newsearch input[name=quickedit]').val();
		if(username=="") {
			toastr.error('You must enter a username');
			newsearch.quickedit.focus();
		} else {
			jQuery.ajax({
				type: 'post',
				url: 'functions/ajaxhelper.php',
				data: 'function=10&username='+username,
				cache: false,
				success: function(response) {
					if(response!="") {
						window.location.href='edituser.php?uid='+response;
					} else {
						toastr.error('Username dont exists');
						newsearch.quickedit.focus();
					}
				}
			});
		}
}

function cspkickuser(username) {
	if(username!="") {
		jQuery.ajax({
			type: 'post',
			url: 'functions/ajaxhelper.php',
			data: 'function=11&username='+username,
			cache: false,
			success: function(response) {
				if(response==1) {
					$('#cspstate-'+username).html('');
					toastr.success(username+' kicked');
				}
			}
		});
	}
}

function csploadsendosd(username) {
	if(username!="") {
		$('#osdusr').val(username);
		$('#osdmsg').val('');
		$('#osdusrlabel').html('<label>Message to <strong>'+username+'</strong></label>');
		$('#modalCspSendOsd').modal({ show: true });
	}
}

function checkcspsendosd() {
	var username=$('input[name=osdusr]').val();
	var message=$('input[name=osdmsg]').val();
		if(message=="") {
			toastr.error('Please enter a message');
			$('#osdmsg').focus();
		} else {
			jQuery.ajax({
				type: 'post',
				url: 'functions/ajaxhelper.php',
				data: 'function=12&username='+username+'&message='+message,
				cache: false,
				success: function(response) {
					if(response==1) {
						$('#modalCspSendOsd').modal('hide');
						toastr.success('Message sent');
					} 
					if(response==2) {
						$('#modalCspSendOsd').modal('hide');
						toastr.warning('No active/compatible newcamd sessions found');
					}
					if(response==0) {
						$('#modalCspSendOsd').modal('hide');
						toastr.error('Message not sent, please try again');
					}
				}
			});
		}
}

function cspgetuserinfo(username) {
	if(username!="") {
		jQuery.ajax({
			type: 'post',
			url: 'functions/ajaxhelper.php',
			data: 'function=13&username='+username,
			cache: false,
			success: function(response) {
				if(response!="") {
					var cspdata=response.split(";");
						$('#cspusr-headusr').html('CSP User Info - '+username);
						$('#cspusr-loginfailures').html(ifempty(cspdata[0]));
						$('#cspusr-sessions').html(ifempty(cspdata[1]));
						$('#cspusr-host').html(ifempty(cspdata[2]));
						$('#cspusr-id').html(ifempty(cspdata[3]));
						$('#cspusr-count').html(ifempty(cspdata[4]));
						$('#cspusr-profile').html(ifempty(cspdata[5]));
						$('#cspusr-clientid').html(ifempty(cspdata[6]));
						$('#cspusr-protocol').html(ifempty(cspdata[7]));
						$('#cspusr-context').html(ifempty(cspdata[8]));
						$('#cspusr-connected').html(ifempty(cspdata[9]));
						$('#cspusr-duration').html(ifempty(cspdata[10]));
						$('#cspusr-ecmcount').html(ifempty(cspdata[11]));
						$('#cspusr-emmcount').html(ifempty(cspdata[12]));
						$('#cspusr-pendingcount').html(ifempty(cspdata[13]));
						$('#cspusr-keepalivecount').html(ifempty(cspdata[14]));
						$('#cspusr-lasttransaction').html(ifempty(cspdata[15]));
						$('#cspusr-lastzap').html(ifempty(cspdata[16]));
						$('#cspusr-idletime').html(ifempty(cspdata[17]));
						$('#cspusr-flags').html(ifempty(cspdata[18]));
						$('#cspusr-avgecminterval').html(ifempty(cspdata[19]));
						$('#cspusr-sessionid').html(ifempty(cspdata[20]));
						$('#cspusr-sessioncdata').html(ifempty(cspdata[21]));
						$('#cspusr-sessionname').html(ifempty(cspdata[22]));
					$('#modalCspUserInfo').modal({ show: true });
				} else {
					toastr.error('Something went wrong, please try again');
				}
			}
		});
	}
}

function cspgetuseripinfo(username) {
	if(username!="") {
		jQuery.ajax({
			type: 'post',
			url: 'functions/ajaxhelper.php',
			data: 'function=14&username='+username,
			cache: false,
			success: function(response) {
				if(response!="") {
					var cspdata=response.split(";");
						$('#cspusrip-headusr').html('CSP User IP Info - '+username);
						$('#cspusrip-ip').html(ifempty(cspdata[0]));
						$('#cspusrip-hostname').html(ifempty(cspdata[1]));
						$('#cspusrip-continent').html(ifempty(cspdata[2]));
						$('#cspusrip-country').html(ifempty(cspdata[3]));
						$('#cspusrip-region').html(ifempty(cspdata[4]));
						$('#cspusrip-city').html(ifempty(cspdata[5]));
						$('#cspusrip-timezone').html(ifempty(cspdata[6]));
						$('#cspusrip-isp').html(ifempty(cspdata[7]));
					$('#modalCspUserIpInfo').modal({ show: true });
				} else {
					toastr.error('Something went wrong, please try again');
				}
			}
		});
	}
}