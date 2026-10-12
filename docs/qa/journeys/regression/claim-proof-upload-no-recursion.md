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
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
changed: true
---

# A claim with a proof file is saved, not a memory fatal

Regression sentinel for `Claim_Proofs::filter_upload_dir()`. The filter that routes proof uploads into `uploads/listora-claim-proofs/` once called `wp_upload_dir()` from inside the `upload_dir` filter, which WordPress applies on every `wp_upload_dir()` call, so it re-entered itself until PHP ran out of memory: every claim with a proof file failed and one attempt wrote ~90 MB to `debug.log`. The filter now reads `basedir` / `baseurl` from the array it is handed.

Roles: a subscriber-level member (claimant) is the actor; another member, logged out and an administrator are the access-control ladder for the stored proof.

## Setup

- A published listing the member does not own and has not claimed (QA site: listing 24 `Sakura House`, author `varundubey`; claimant `qa_vendor_03`).
- A tiny image to upload (any PNG/JPEG/GIF/WebP or PDF under 5 MB; the modal hint reads "Business license, utility bill, or official document (JPEG, PNG, GIF, WebP or PDF, max 5 MB).").
- Debug logging on, `wp-content/debug.log` present.

## Steps

### 1. Note the log size
- **Action**: `ls -l wp-content/debug.log`.

### 2. Submit a claim with a proof file, as a member
- **Action**: log in as the member, open the listing, click **Claim**, enter text in `#listora-claim-proof`, choose a file in `#listora-claim-proof-file`, click **Submit Claim**. Or `curl -X POST $SITE_URL/wp-json/listora/v1/claims -H "X-WP-Nonce: <nonce>" -F listing_id=24 -F "proof_text=QA-J1- probe" -F "proof_file=@proof.png;type=image/png"`.
- **Expect**: HTTP 201 `{"id":N,"status":"pending","message":"Your claim has been submitted and is under review.","proof_file_uploaded":true}`. In the browser the form body is replaced by "Claim submitted — we'll email you when it's reviewed." with a **View my claims** link.

### 3. The proof is private and in the protected directory
- **Expect**: `SELECT proof_files FROM wp_listora_claims WHERE id=N` is a JSON array of attachment IDs; each attachment has `post_status = private`, its file lives under `wp-content/uploads/listora-claim-proofs/` with a random `proof-<32 chars>.<ext>` name, and carries post meta `_listora_claim_proof = 1`.
- **Role ladder**: a direct request to the file URL as logged out and as another member is refused by the server rule written by `Claim_Proofs::protect()`; the admin Claims screen drawer (**Details**) links the proof through `admin-post.php?action=wb_listora_claim_proof` for a user with `manage_listora_claims` only. Administrator is the control.

### 4. Nothing fatal was logged
- **Expect**: `debug.log` did not grow with `Allowed memory size` or `class-claim-proofs.php` frames.

### 5. Ordinary uploads are unaffected
- **Action**: immediately after step 2 run `wp eval 'echo wp_upload_dir()["subdir"];'` and upload any file in the Media Library.
- **Expect**: the dated folder (`/YYYY/MM`), not the proofs directory: the filter is removed after the upload.

## Cleanup

Delete the claim (Claims screen **Delete**, or `DELETE /wp-json/listora/v1/claims/{id}`), `wp post delete <attachment> --force`, and remove the matching `claim_submitted` audit row.

## Fail diagnostics

- Fatal on submit: `includes/core/class-claim-proofs.php:186` (`filter_upload_dir()` must read `$dirs`, not call `wp_upload_dir()`).
- Media Library uploads land in the proofs folder: `includes/core/class-claim-proofs.php:203-211` (`with_private_dir()` must remove the filter in `finally`).
- Attachment not private: `includes/rest/class-claims-controller.php` around line 390 (`'post_status' => 'private'`).
- `proof_file_uploaded` missing: `includes/rest/class-claims-controller.php:276`.
- PHPUnit: `tests/integration/ClaimProofUploadDirTest.php`.

## Not verified

Steps 2 (REST path), 3 (private status, directory, name, `_listora_claim_proof`), 4 and 5 (`subdir`) were run live with curl + wp-cli as `qa_vendor_03` and cleaned up. The modal upload through the browser, and the logged-out / other-member file URL refusal, were not run.
