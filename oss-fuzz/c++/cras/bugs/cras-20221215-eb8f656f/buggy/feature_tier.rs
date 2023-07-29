// Copyright 2022 The ChromiumOS Authors
// Use of this source code is governed by a BSD-style license that can be
// found in the LICENSE file.

/// Support status for CRAS features.
#[repr(C)]
pub struct CrasFeatureTier {
    pub sr_bt_supported: bool,
}

impl CrasFeatureTier {
    /// Construct a CrasFeatureTier. `board_name` should be the name of the
    /// reference board. `cpu_name` should be the model name of the CPU.
    pub fn new(board_name: &str, cpu_name: &str) -> Self {
        Self {
            sr_bt_supported: match board_name {
                "eve" | "soraka" | "nautilus" | "nami" | "atlas" | "nocturne" | "rammus"
                | "fizz" => {
                    let cpu_name_lowercase = cpu_name.to_lowercase();
                    !cpu_name_lowercase.contains("celeron")
                        && !cpu_name_lowercase.contains("pentium")
                }
                _ => false,
            },
        }
    }
}

#[cfg(test)]
mod tests {
    use crate::feature_tier::CrasFeatureTier;

    #[test]
    fn eve_i7() {
        let tier = CrasFeatureTier::new("eve", "Intel(R) Core(TM) i7-7Y75 CPU @ 1.30GHz");
        assert!(tier.sr_bt_supported);
    }

    #[test]
    fn random_board() {
        let tier = CrasFeatureTier::new("random-board", "random");
        assert_eq!(tier.sr_bt_supported, false);
    }

    #[test]
    fn fizz_celeron() {
        let tier = CrasFeatureTier::new("fizz", "Celeron-3865U");
        assert_eq!(tier.sr_bt_supported, false);
    }

    #[test]
    fn nami_pentium() {
        let tier = CrasFeatureTier::new("nami", "PENTIUM-4417U");
        assert_eq!(tier.sr_bt_supported, false);
    }
}

pub mod bindings {
    use std::ffi::{CStr, CString};

    pub use super::CrasFeatureTier;

    #[no_mangle]
    /// Initialize the cras feature tier struct.
    /// On error, a negative error code is returned.
    pub unsafe extern "C" fn cras_feature_tier_init(
        out: *mut CrasFeatureTier,
        board_name: *const libc::c_char,
        cpu_name: *const libc::c_char,
    ) -> libc::c_int {
        let empty = CString::new("").unwrap();
        let board_name = if board_name.is_null() {
            &empty
        } else {
            CStr::from_ptr(board_name)
        };
        let board_name = match board_name.to_str() {
            Ok(name) => name,
            Err(_) => return -libc::EINVAL,
        };
        let cpu_name = if cpu_name.is_null() {
            &empty
        } else {
            CStr::from_ptr(cpu_name)
        };
        let cpu_name = match cpu_name.to_str() {
            Ok(name) => name,
            Err(_) => return -libc::EINVAL,
        };

        *out = CrasFeatureTier::new(board_name, cpu_name);

        0
    }

    #[cfg(test)]
    mod tests {
        use super::*;

        #[test]
        fn null_safety() {
            let mut tier = std::mem::MaybeUninit::<CrasFeatureTier>::zeroed();
            let rc = unsafe {
                cras_feature_tier_init(tier.as_mut_ptr(), std::ptr::null(), std::ptr::null())
            };
            assert_eq!(0, rc);
        }
    }
}
