# VanAssist Outlook Outreach

Windows GUI for review-first, individual organisation outreach using Classic
Outlook and the evidence-backed CSV:

`database/seeds/outreach/vanassist-organisations.csv`

Backlog: **COM-002 / COM-003**. The application does not alter production data
and does not store Microsoft credentials.

## Safety controls

- fixed sender: `support@vanassist.com.au`;
- one separately personalised message per recipient (never BCC bulk mail);
- explicit no-unsolicited, personal/ambiguous, invalid and stale rows are held;
- maximum 25 live recipients per batch;
- an internal test must be sent in every application session before external
  sending is enabled;
- operator can preview and edit every subject/body;
- draft-only mode is available;
- each draft/send attempt is logged under
  `%LOCALAPPDATA%\VanAssistOutreach\send-log.csv`;
- copy identifies VanAssist, explains why the role was contacted, requests no
  member/customer data, makes no implied endorsement, and supports reply-based
  opt-out.

These controls support careful operation but do not replace legal advice or the
sender's obligations under the Spam Act and ACMA guidance.

## Requirements

- Windows 10/11;
- **Classic Outlook for Windows** (New Outlook does not expose the COM API);
- an Outlook account or shared mailbox with permission to send as
  `support@vanassist.com.au`;
- Python 3.11+ only when running from source.

For a shared mailbox, Microsoft 365 must grant the signed-in user **Send As** (or
Send on Behalf) permission. Run the internal test and inspect the actual From
header before sending externally.

## Run from source

```powershell
python tools/outlook_bulk_mailer/vanassist_outreach_mailer.py
```

## Build the EXE

From the repository root:

```powershell
python -m pip install -r tools/outlook_bulk_mailer/requirements-build.txt
pyinstaller --noconfirm --clean --onefile --windowed `
  --name VanAssist-Outlook-Outreach `
  --hidden-import win32com.client `
  --hidden-import pythoncom `
  tools/outlook_bulk_mailer/vanassist_outreach_mailer.py
```

Output:

`dist/VanAssist-Outlook-Outreach.exe`

The EXE expects the default CSV at the repository path, but the GUI can browse
to any compatible CSV.

## Recommended first use

1. Open Classic Outlook and confirm `support@vanassist.com.au` is available.
2. Start the application and load the CSV.
3. Send the internal test to an address you control.
4. Confirm the email genuinely arrived **from** `support@vanassist.com.au`.
5. Select one contact, preview/edit it, and create an Outlook draft.
6. Send a pilot of 5–10 peak bodies/federations.
7. Review replies, bounces and opt-outs before another batch.
8. Never send the held RACQ row.
