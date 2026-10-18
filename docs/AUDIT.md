## Resolution Log (2026-10-18)

All Tier 1, Tier 2, Tier 3, and Tier 4 findings from this audit have been closed. Test suite went from 31 passing (166 assertions) to 42 passing (242 assertions).

Commits:
- <hash> fix(security): remove CSRF exemption for auth/* routes
- <hash> chore: gitignore public/uploads
- <hash> fix(auth): redirect form-based logouts
- <hash> test: isolate test suite to kanlungan_test
- <hash> fix(security): rate limit OTPs by email + IP
- <hash> fix(security): fix register limiter for shared campus IPs
- <hash> fix(security): enforce max image dimensions
- <hash> fix(security): prevent moderators from banning higher-privilege accounts
- <hash> fix(data): prevent audit log cascade deletes
- <hash> fix(security): throw exception in production if mailer is log
- <hash> fix(security): hide user emails from moderator queue
- <hash> feat(moderation): add unmask route for admin/dpo
- <hash> fix(security): enable TrustHosts and sanitize alert redirects
- <hash> fix(moderation): sort by severity before queue limit
- <hash> fix(moderation): moderator dismissal overrides community vote
- <hash> feat(moderation): add unban and unmute actions
- <hash> fix(security): delete photos when content is removed
- <hash> test(auth): add auth flow feature tests