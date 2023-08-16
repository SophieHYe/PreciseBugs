/*
  Copyright (c) 2012, Nils Schneider <nils@nilsschneider.net>
  Copyright (c) 2016, Matthias Schiffer <mschiffer@universe-factory.net>
  All rights reserved.

  Redistribution and use in source and binary forms, with or without
  modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
       this list of conditions and the following disclaimer.
    2. Redistributions in binary form must reproduce the above copyright notice,
       this list of conditions and the following disclaimer in the documentation
       and/or other materials provided with the distribution.

  THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
  AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
  IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
  DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
  FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
  DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
  SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
  CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
  OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
  OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/


#include <ecdsautil/ecdsa.h>
#include <ecdsautil/sha256.h>

#include <string.h>


bool ecdsa_is_valid_pubkey(const ecc_25519_work_t *pubkey) {
  ecc_25519_work_t work;

  if (ecc_25519_is_identity(pubkey))
    return false;

  // q * pubkey should be identity element
  ecc_25519_scalarmult(&work, &ecc_25519_gf_order, pubkey);
  return ecc_25519_is_identity(&work);
}


// Generate k according to RFC6979 (Deterministic DSA/ECDSA)
static void generate_k_prepare(uint8_t V[32], uint8_t K[32], const uint8_t x[32], const uint8_t m[32]) {
  memset(V, 0x01, 32); // b.
  memset(K, 0x00, 32); // c.

  {
    uint8_t input[32+1+32+32];
    memcpy(input, V, 32);
    input[32] = 0x00;
    memcpy(input+32+1, x, 32);
    memcpy(input+32+1+32, m, 32);
    ecdsa_sha256_hmac(K, K, input, sizeof(input)); // d.
  }

  ecdsa_sha256_hmac(V, K, V, 32); // e.

  {
    uint8_t input[32+1+32+32];
    memcpy(input, V, 32);
    input[32] = 0x01;
    memcpy(input+32+1, x, 32);
    memcpy(input+32+1+32, m, 32);
    ecdsa_sha256_hmac(K, K, input, sizeof(input)); // f.
  }

  ecdsa_sha256_hmac(V, K, V, 32); // g.
}

static void generate_k(uint8_t k[32], uint8_t V[32], uint8_t K[32]) {
  // h.
  // Note that T = V, as qlen = hlen
  ecdsa_sha256_hmac(V, K, V, 32);
  memcpy(k, V, 32);

  // The following steps are preparation for the next iteration (in case the generated k is invalid)
  {
    uint8_t input[32+1];
    memcpy(input, V, 32);
    input[32] = 0x00;
    ecdsa_sha256_hmac(K, K, input, sizeof(input));
  }
  ecdsa_sha256_hmac(V, K, V, 32);
}

void ecdsa_sign_legacy(ecdsa_signature_t *signature, const ecc_int256_t *hash, const ecc_int256_t *secret) {
  ecc_int256_t hash_r, k, krecip, tmp;
  ecc_25519_work_t kG;
  uint8_t V[32], K[32];

  // Reduce hash (instead of clearing 3 bits)
  ecc_25519_gf_reduce(&hash_r, hash);

  // Generate k
  generate_k_prepare(V, K, secret->p, hash_r.p);

regenerate:
  generate_k(k.p, V, K);
  ecc_25519_gf_sanitize_secret(&k, &k);

  // calculate k^(-1)
  ecc_25519_gf_recip(&krecip, &k);

  // calculate kG = k * base point
  ecc_25519_scalarmult_base(&kG, &k);

  // store x coordinate of kG in r
  ecc_25519_store_xy_legacy(&tmp, NULL, &kG);
  ecc_25519_gf_reduce(&signature->r, &tmp);

  if (ecc_25519_gf_is_zero(&signature->r))
    goto regenerate;

  // tmp = r * secret
  ecc_25519_gf_mult(&tmp, &signature->r, secret);

  // s = hash + tmp = hash + r * secret
  ecc_25519_gf_add(&signature->s, &hash_r, &tmp);

  // tmp = krecip * s = k^(-1) * s
  ecc_25519_gf_mult(&tmp, &krecip, &signature->s);

  // mod n (order of G)
  ecc_25519_gf_reduce(&signature->s, &tmp);

  if (ecc_25519_gf_is_zero(&signature->s))
    goto regenerate;
}


void ecdsa_verify_prepare_legacy(ecdsa_verify_context_t *ctx, const ecc_int256_t *hash, const ecdsa_signature_t *signature) {
  ecc_int256_t w, u1, tmp;

  if (ecc_25519_gf_is_zero(&signature->s) || ecc_25519_gf_is_zero(&signature->r)) {
    // Signature is invalid, mark by setting ctx->r to an invalid value
    memset(&ctx->r, 0, sizeof(ctx->r));
    return;
  }

  ctx->r = signature->r;

  ecc_25519_gf_recip(&w, &signature->s);
  ecc_25519_gf_reduce(&tmp, hash);
  ecc_25519_gf_mult(&u1, &tmp, &w);
  ecc_25519_gf_mult(&ctx->u2, &ctx->r, &w);
  ecc_25519_scalarmult_base(&ctx->s1, &u1);
}


bool ecdsa_verify_legacy(const ecdsa_verify_context_t *ctx, const ecc_25519_work_t *pubkey) {
  ecc_25519_work_t s2, work;
  ecc_int256_t w, tmp;

  // Signature was detected as invalid in prepare step
  if (ecc_25519_gf_is_zero(&ctx->r))
    return false;

  ecc_25519_scalarmult(&s2, &ctx->u2, pubkey);
  ecc_25519_add(&work, &ctx->s1, &s2);
  ecc_25519_store_xy_legacy(&w, NULL, &work);
  ecc_25519_gf_sub(&tmp, &ctx->r, &w);

  return ecc_25519_gf_is_zero(&tmp);
}


size_t ecdsa_verify_list_legacy(const ecdsa_verify_context_t *ctxs, size_t n_ctxs, const ecc_25519_work_t *pubkeys, size_t n_pubkeys) {
  size_t ret = 0, i, j;

  bool used[n_pubkeys];
  memset(used, 0, sizeof(used));

  for (i = 0; i < n_ctxs; i++) {
    for (j = 0; j < n_pubkeys; j++) {
      if (used[j])
        continue;

      if (ecdsa_verify_legacy(&ctxs[i], &pubkeys[j])) {
        ret++;
        used[j] = true;
        break;
      }
    }
  }

  return ret;
}
