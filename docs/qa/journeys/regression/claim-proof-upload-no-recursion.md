---
journey: claim-proof-upload-no-recursion
plugin: wb-listora
priority: critical
roles: [subscriber]
covers: [claims, claim-proof-upload]
prerequisites:
  - "A published listing the member does not own and has not claimed"
estimated_runtime_minutes: 4
covers_card: 10327885984
---

# A claim with a proof file is saved, not a memory fatal

Regression sentinel for `Claim_Proofs::filter_upload_dir()`.

## Background

The filter that routes proof uploads into `uploads/listora-claim-proofs/` called `wp_upload_dir()` from inside the `upload_dir` filter. WordPress applies that filter on every `wp_upload_dir()` call, so the callback re-entered itself until PHP ran out of memory. Every claim with a proof file failed, and one attempt wrote ~90 MB to debug.log. The filter now reads `basedir` / `baseurl` from the array it is handed.

## Steps

### 1. Note the log size
- **Action**: `ls -l wp-content/debug.log`.

### 2. Submit a claim with a proof file, as a member
- **Action**: log in as a subscriber, open the listing, submit the claim modal with a small image attached (or `POST /wp-json/listora/v1/claims` multipart with `listing_id`, `proof_text`, `proof_file` and the REST nonce).
- **Expect**: 201, `"status":"pending"`, `"proof_file_uploaded":true`.

### 3. The proof is private and in the protected directory
- **Expect**: the claim row's `proof_files` attachment resolves under `uploads/listora-claim-proofs/` with post status `private`.

### 4. Nothing fatal was logged
- **Expect**: debug.log did not grow with `Allowed memory size` or `class-claim-proofs.php` frames.

### 5. Ordinary uploads are unaffected
- **Expect**: `wp_upload_dir()['subdir']` after the claim is the dated folder (`/YYYY/MM`), not the proofs directory - the filter is removed after the upload.

## Fail diagnostics
- Fatal on submit → `includes/core/class-claim-proofs.php` `filter_upload_dir()` calling `wp_upload_dir()` again.
- Media library uploads land in the proofs folder → `with_private_dir()` no longer removes the filter in `finally`.
- PHPUnit: `tests/integration/ClaimProofUploadDirTest.php`.
