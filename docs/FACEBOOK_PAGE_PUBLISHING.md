# Facebook Page publishing

Social Studio can publish an approved Facebook asset and its reviewed caption
directly to the matching brand Page. Draft and Instagram assets cannot be sent.
The application records the returned Facebook post ID, publisher, time and any
failure so staff can verify or retry without guessing.

## External setup

Create or use a Meta business app, grant the Page publishing permissions Meta
requires, obtain the Page ID and Page access token, and store both only in the
production secret environment. Configure the matching `FACEBOOK_*` variables
listed in `.env.example`. The control remains disabled while either value is
missing. Never put a live Page token in source control or an administrator note.

Meta also supports native Page scheduling in Business Suite. The first platform
release intentionally uses an explicit approval and publish action; unattended
scheduling should be enabled only after live Page publishing, token rotation and
failure-alert acceptance are complete.

## Acceptance

1. Generate a Facebook-format Social Studio asset.
2. Approve it.
3. Publish it from the matching brand admin.
4. Confirm the image and caption on the intended Page.
5. Confirm the stored post ID and audit event.
6. Rotate/revoke the token and confirm publishing fails visibly without exposing
   the token.

Rollback disables the environment credentials. Existing Facebook posts are not
deleted automatically and must be managed on the Page.
