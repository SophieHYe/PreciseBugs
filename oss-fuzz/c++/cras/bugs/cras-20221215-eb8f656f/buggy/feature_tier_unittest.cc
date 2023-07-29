// Copyright 2022 The ChromiumOS Authors
// Use of this source code is governed by a BSD-style license that can be
// found in the LICENSE file.

#include <gtest/gtest.h>

extern "C" {
#include "cras_feature_tier.h"
}

namespace {

TEST(FeatureTierTest, EveI7) {
  cras_feature_tier tier;
  cras_feature_tier_init(&tier, "eve",
                         "Intel(R) Core(TM) i7-7Y75 CPU @ 1.30GHz");
  EXPECT_EQ(tier.sr_bt_supported, true);
}

TEST(FeatureTierTest, RandomCeleron) {
  cras_feature_tier tier;
  cras_feature_tier_init(&tier, "random-board", "celeron");
  EXPECT_EQ(tier.sr_bt_supported, false);
}

}  // namespace
