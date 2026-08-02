"""Review-first VanAssist organisation outreach through Classic Outlook.

This Windows desktop program loads the evidence-backed organisation CSV,
personalises role-relevant messages, excludes unsafe rows, and creates drafts or
sends a selected batch through the configured Outlook profile.

It never stores Outlook credentials. Direct sending requires an internal test
in the current session and is capped at 25 recipients.
"""

from __future__ import annotations

import csv
import html
import os
import re
import sys
import time
from dataclasses import dataclass, replace
from datetime import date, datetime, timedelta
from pathlib import Path
from typing import Any, Iterable

import tkinter as tk
from tkinter import filedialog, messagebox, ttk

APP_NAME = "VanAssist Outlook Outreach"
SENDER_ADDRESS = "support@vanassist.com.au"
SENDER_NAME = "Glen Condren"
SITE_URL = "https://vanassist.com.au/"
MAX_LIVE_BATCH = 25
SOURCE_MAX_AGE_DAYS = 180
DEFAULT_DELAY_SECONDS = 2.0

SOURCE_ROOT = Path(__file__).resolve().parents[2]


def find_default_csv() -> Path:
    candidates = [
        SOURCE_ROOT / "database" / "seeds" / "outreach" / "vanassist-organisations.csv",
        Path.cwd() / "database" / "seeds" / "outreach" / "vanassist-organisations.csv",
    ]
    if getattr(sys, "frozen", False):
        exe_dir = Path(sys.executable).resolve().parent
        candidates.extend(
            [
                exe_dir / "database" / "seeds" / "outreach" / "vanassist-organisations.csv",
                exe_dir.parent
                / "database"
                / "seeds"
                / "outreach"
                / "vanassist-organisations.csv",
            ]
        )
    return next((candidate for candidate in candidates if candidate.exists()), candidates[0])


DEFAULT_CSV = find_default_csv()
LOG_DIR = Path(os.environ.get("LOCALAPPDATA", Path.home())) / "VanAssistOutreach"
LOG_PATH = LOG_DIR / "send-log.csv"


@dataclass(frozen=True)
class Contact:
    index: int
    organisation_name: str
    organisation_type: str
    coverage: str
    state_code: str
    website_url: str
    contact_role: str
    email: str
    source_url: str
    source_checked_at: str
    publication_context: str
    relevance_reason: str
    no_unsolicited_warning: bool
    personal_or_ambiguous: bool
    notes: str

    @property
    def exclusion_reason(self) -> str:
        if self.no_unsolicited_warning:
            return "Explicit no-unsolicited warning"
        if self.personal_or_ambiguous:
            return "Personal or ambiguous address"
        if not valid_email(self.email):
            return "Invalid email"
        try:
            checked = date.fromisoformat(self.source_checked_at)
        except ValueError:
            return "Invalid source-check date"
        if checked < date.today() - timedelta(days=SOURCE_MAX_AGE_DAYS):
            return "Source is older than 180 days"
        return ""

    @property
    def safe(self) -> bool:
        return self.exclusion_reason == ""


@dataclass
class MessageCopy:
    subject: str
    body_text: str


def truthy(value: str) -> bool:
    return value.strip().lower() in {"1", "true", "yes", "y"}


def valid_email(value: str) -> bool:
    return re.fullmatch(r"[^@\s]+@[^@\s]+\.[^@\s]+", value.strip()) is not None


def load_contacts(path: Path) -> list[Contact]:
    required = {
        "organisation_name",
        "organisation_type",
        "coverage",
        "state_code",
        "website_url",
        "contact_role",
        "email",
        "source_url",
        "source_checked_at",
        "publication_context",
        "relevance_reason",
        "no_unsolicited_warning",
        "personal_or_ambiguous",
        "notes",
    }
    with path.open("r", encoding="utf-8-sig", newline="") as handle:
        reader = csv.DictReader(handle)
        missing = required.difference(reader.fieldnames or [])
        if missing:
            raise ValueError("CSV is missing columns: " + ", ".join(sorted(missing)))
        contacts: list[Contact] = []
        seen: set[str] = set()
        for index, row in enumerate(reader, start=1):
            email = (row["email"] or "").strip().lower()
            duplicate = email in seen
            seen.add(email)
            contact = Contact(
                index=index,
                organisation_name=(row["organisation_name"] or "").strip(),
                organisation_type=(row["organisation_type"] or "").strip(),
                coverage=(row["coverage"] or "").strip(),
                state_code=(row["state_code"] or "").strip(),
                website_url=(row["website_url"] or "").strip(),
                contact_role=(row["contact_role"] or "").strip(),
                email=email,
                source_url=(row["source_url"] or "").strip(),
                source_checked_at=(row["source_checked_at"] or "").strip(),
                publication_context=(row["publication_context"] or "").strip(),
                relevance_reason=(row["relevance_reason"] or "").strip(),
                no_unsolicited_warning=truthy(row["no_unsolicited_warning"] or "") or duplicate,
                personal_or_ambiguous=truthy(row["personal_or_ambiguous"] or ""),
                notes=(row["notes"] or "").strip(),
            )
            contacts.append(contact)
    return contacts


def style_for(contact_type: str) -> str:
    return {
        "club": "club",
        "club_federation": "club",
        "touring_association": "club",
        "industry_association": "industry",
        "manufacturer": "fleet",
        "dealer_network": "fleet",
        "rental_fleet": "fleet",
        "publication": "editorial",
        "tourism_body": "tourism",
        "park_network": "tourism",
    }.get(contact_type, "tourism")


def area_for(contact: Contact) -> str:
    if contact.coverage.lower() == "national":
        return "Australia"
    if contact.coverage and contact.state_code:
        return f"{contact.coverage} ({contact.state_code})"
    return contact.coverage or contact.state_code or "Australia"


def greeting_for(contact: Contact) -> str:
    role = contact.contact_role.strip()
    if not role or re.match(r"^(reception|general|admin|administration)", role, re.I):
        return f"Hello {contact.organisation_name} team,"
    return f"Hello {role} at {contact.organisation_name},"


def build_message(contact: Contact) -> MessageCopy:
    organisation = contact.organisation_name
    role = contact.contact_role or "organisation"
    area = area_for(contact)
    reason = contact.relevance_reason.rstrip(".") + "."
    style = style_for(contact.organisation_type)

    if style == "club":
        subject = f"A free Australian travel-help resource for {organisation} members to review"
        paragraphs = [
            f"I am writing to your published {role} contact because VanAssist may be useful "
            f"to people travelling with caravans, motorhomes and RVs across {area}.",
            "VanAssist has launched as a free service for travellers. It helps people find "
            "nearby caravan and vehicle services, fuel, EV charging and caravan-suitable "
            "places to stay. Listings show whether information is claimed or verified, and "
            "public-source details should still be checked before relying on them.",
            f"Why this note to {organisation}: {reason}",
            f"Would your committee be willing to review the site? If you consider it "
            f"genuinely useful, you are welcome to share it with members in whatever way "
            f"suits {organisation}. We are not asking for your member list.",
        ]
    elif style == "industry":
        subject = f"VanAssist collaboration enquiry for {organisation}"
        paragraphs = [
            f"I am contacting your published {role} role at {organisation} because VanAssist "
            f"is a free, national location-first directory for caravan and RV travellers, "
            f"including people travelling through {area}.",
            "The platform helps travellers find relevant repairers, mobile technicians, "
            "parts, fuel, charging and caravan-suitable stays. We are also working to "
            "improve listing accuracy, source transparency and provider claim workflows.",
            f"Why this note: {reason}",
            f"I would value a short discussion about whether {organisation} can help direct "
            "listing-accuracy questions to the right channel, and whether the traveller "
            "resource may be appropriate for your members or audience.",
        ]
    elif style == "fleet":
        subject = f"A free location-based support resource — enquiry for {organisation}"
        paragraphs = [
            f"I am writing to your published {role} contact at {organisation} because "
            f"VanAssist may complement the support your owners or renters already receive "
            f"across {area}.",
            "VanAssist is free for travellers and helps them find relevant caravan/RV "
            "repairers, mobile help, fuel, charging and suitable stays near them or along a "
            "route. It is designed for phones and does not require an app install.",
            f"Why this note: {reason}",
            "I would welcome a simple resource or data collaboration discussion—particularly "
            "keeping dealer, service and support locations accurate. This is not a request "
            "for customer data.",
        ]
    elif style == "editorial":
        subject = (
            f"Story lead for {organisation}: free location-first tool for Australian "
            "caravan travellers"
        )
        paragraphs = [
            f"I am sending this to your published {role} contact at {organisation} as a "
            "possible reader-service story, not asking for access to your subscriber list.",
            "VanAssist has launched as a free Australian platform that helps caravan, "
            "motorhome and RV travellers find nearby repairs, mobile help, fuel, charging "
            "and caravan-suitable places to stay. It is location-first, works in a phone "
            "browser, and makes claimed, verified and public-source listing status visible.",
            f"Why this may interest {organisation}: {reason}",
            "The useful story is also the difficult bit: building a genuinely accurate "
            "national service directory and giving travellers a simple way to report gaps "
            "or corrections.",
            "If it interests your editor, I can provide background, screenshots and answer "
            "questions.",
        ]
    else:
        subject = f"A free road-travel support resource — enquiry for {organisation}"
        paragraphs = [
            f"I am contacting your published {role} role at {organisation} because "
            f"VanAssist may help caravan and RV visitors travel more confidently through "
            f"{area}.",
            "The free mobile-friendly website helps travellers find nearby repair and mobile "
            "services, fuel, charging and caravan-suitable stays. It can also expose service "
            "or accommodation gaps that matter on well-used touring routes.",
            f"Why this note: {reason}",
            "Would your team be willing to review the site and advise whether it belongs in "
            "your visitor or industry resources?",
        ]

    body = "\n\n".join(
        [
            greeting_for(contact),
            *paragraphs,
            f"You can review the live service at {SITE_URL}",
            "If this is outside your role, a pointer to the correct contact would help. "
            "Otherwise reply with “unsubscribe” and we will not contact this address again.",
            "We are not asking for member, subscriber or customer lists, and we will not "
            "imply endorsement or partnership without your agreement.",
            f"Regards,\n{SENDER_NAME}\nVanAssist\n{SENDER_ADDRESS}\n{SITE_URL}",
        ]
    )
    return MessageCopy(subject=subject, body_text=body)


def text_to_html(body: str) -> str:
    blocks = []
    for paragraph in body.split("\n\n"):
        safe = html.escape(paragraph).replace("\n", "<br>")
        safe = safe.replace(
            html.escape(SITE_URL),
            f'<a href="{html.escape(SITE_URL)}">{html.escape(SITE_URL)}</a>',
        )
        blocks.append(f"<p>{safe}</p>")
    return (
        '<html><body style="font-family:Arial,sans-serif;font-size:11pt;color:#202124">'
        + "".join(blocks)
        + "</body></html>"
    )


def outlook_app() -> Any:
    try:
        import win32com.client  # type: ignore[import-untyped]
    except ImportError as exc:
        raise RuntimeError(
            "Microsoft Outlook support is not installed. Reinstall the packaged application."
        ) from exc
    try:
        return win32com.client.Dispatch("Outlook.Application")
    except Exception as exc:
        raise RuntimeError(
            "Classic Outlook could not be opened. This tool requires Classic Outlook for "
            "Windows; the New Outlook does not expose the required COM interface."
        ) from exc


def find_sender_account(outlook: Any, sender: str) -> Any | None:
    accounts = outlook.Session.Accounts
    for index in range(1, int(accounts.Count) + 1):
        account = accounts.Item(index)
        address = str(getattr(account, "SmtpAddress", "") or "").strip().lower()
        if address == sender.lower():
            return account
    return None


def sender_mailbox_mounted(outlook: Any, sender: str) -> bool:
    session = outlook.Session
    stores = session.Stores
    for index in range(1, int(stores.Count) + 1):
        name = str(getattr(stores.Item(index), "DisplayName", "") or "").strip()
        if not name:
            continue
        try:
            recipient = session.CreateRecipient(name)
            recipient.Resolve()
            if not recipient.Resolved:
                continue
            exchange_user = recipient.AddressEntry.GetExchangeUser()
            address = exchange_user.PrimarySmtpAddress if exchange_user else recipient.Address
        except Exception:
            continue
        if str(address or "").strip().lower() == sender.lower():
            return True
    return False


def sender_status(outlook: Any, sender: str) -> tuple[str, str]:
    """Classify how (or whether) Outlook can send as ``sender``.

    Exchange Online grants Full Access and Send As independently, so a mounted
    shared mailbox proves nothing about permission to send from it.
    """
    if find_sender_account(outlook, sender) is not None:
        return "account", f"{sender} is a configured Outlook account."
    if sender_mailbox_mounted(outlook, sender):
        return (
            "shared-mailbox",
            f"{sender} is mounted as a shared mailbox but is not a sending account. "
            "Exchange will reject the send unless Send As permission has been granted "
            "to the signed-in user.",
        )
    return (
        "unavailable",
        f"{sender} is neither an Outlook account nor a mounted mailbox in this profile.",
    )


def configure_sender(mail: Any, outlook: Any, sender: str) -> str:
    account = find_sender_account(outlook, sender)
    if account is not None:
        mail.SendUsingAccount = account
        return "account"
    # Shared mailboxes generally require Exchange Send As / Send on Behalf permission.
    mail.SentOnBehalfOfName = sender
    return "shared-mailbox"


def create_outlook_message(
    outlook: Any,
    contact: Contact,
    copy: MessageCopy,
    *,
    display: bool,
    send: bool,
) -> str:
    mail = outlook.CreateItem(0)
    mail.To = contact.email
    mail.Subject = copy.subject
    mail.HTMLBody = text_to_html(copy.body_text)
    mail.ReplyRecipients.Add(SENDER_ADDRESS)
    mode = configure_sender(mail, outlook, SENDER_ADDRESS)
    if send:
        mail.Send()
    elif display:
        mail.Display()
    else:
        mail.Save()
    return mode


def log_attempt(contact: Contact, action: str, result: str, details: str = "") -> None:
    LOG_DIR.mkdir(parents=True, exist_ok=True)
    exists = LOG_PATH.exists()
    with LOG_PATH.open("a", encoding="utf-8", newline="") as handle:
        writer = csv.writer(handle)
        if not exists:
            writer.writerow(
                [
                    "timestamp",
                    "action",
                    "organisation",
                    "email",
                    "result",
                    "details",
                    "source_url",
                ]
            )
        writer.writerow(
            [
                datetime.now().astimezone().isoformat(timespec="seconds"),
                action,
                contact.organisation_name,
                contact.email,
                result,
                details,
                contact.source_url,
            ]
        )


class MailerGui(tk.Tk):
    def __init__(self) -> None:
        super().__init__()
        self.title(APP_NAME)
        self.geometry("1280x760")
        self.minsize(980, 620)
        self.contacts: list[Contact] = []
        self.contact_by_item: dict[str, Contact] = {}
        self.overrides: dict[int, MessageCopy] = {}
        self.test_sent = False
        self.csv_path = tk.StringVar(value=str(DEFAULT_CSV))
        self.filter_text = tk.StringVar()
        self.status_text = tk.StringVar(value="Load the CSV to begin.")
        self.test_recipient = tk.StringVar(value=SENDER_ADDRESS)
        self._build()
        if DEFAULT_CSV.exists():
            self.load_csv()

    def _build(self) -> None:
        style = ttk.Style(self)
        style.configure("Treeview", rowheight=25)

        top = ttk.Frame(self, padding=10)
        top.pack(fill=tk.X)
        ttk.Label(top, text="Organisation CSV:").grid(row=0, column=0, sticky=tk.W)
        ttk.Entry(top, textvariable=self.csv_path, width=82).grid(
            row=0, column=1, padx=6, sticky=tk.EW
        )
        ttk.Button(top, text="Browse…", command=self.browse).grid(row=0, column=2)
        ttk.Button(top, text="Load", command=self.load_csv).grid(row=0, column=3, padx=(6, 0))
        top.columnconfigure(1, weight=1)

        safety = ttk.LabelFrame(self, text="Safety and sender", padding=10)
        safety.pack(fill=tk.X, padx=10, pady=(0, 8))
        ttk.Label(
            safety,
            text=(
                f"From: {SENDER_NAME} <{SENDER_ADDRESS}>  •  Classic Outlook required  •  "
                f"Maximum live batch: {MAX_LIVE_BATCH}  •  Test required each session"
            ),
        ).grid(row=0, column=0, columnspan=6, sticky=tk.W)
        ttk.Label(safety, text="Internal test recipient:").grid(
            row=1, column=0, pady=(8, 0), sticky=tk.W
        )
        ttk.Entry(safety, textvariable=self.test_recipient, width=35).grid(
            row=1, column=1, pady=(8, 0), padx=6
        )
        ttk.Button(safety, text="Send internal test", command=self.send_test).grid(
            row=1, column=2, pady=(8, 0)
        )
        ttk.Label(
            safety,
            text="A public role address is not blanket consent. Review relevance and source before sending.",
            foreground="#8a3b00",
        ).grid(row=1, column=3, columnspan=3, padx=(18, 0), pady=(8, 0), sticky=tk.W)

        controls = ttk.Frame(self, padding=(10, 0, 10, 8))
        controls.pack(fill=tk.X)
        ttk.Label(controls, text="Filter:").pack(side=tk.LEFT)
        search = ttk.Entry(controls, textvariable=self.filter_text, width=35)
        search.pack(side=tk.LEFT, padx=6)
        search.bind("<KeyRelease>", lambda _event: self.refresh_tree())
        ttk.Button(controls, text="Select first 25 safe", command=self.select_first_safe).pack(
            side=tk.LEFT, padx=4
        )
        ttk.Button(controls, text="Clear selection", command=self.clear_selection).pack(
            side=tk.LEFT, padx=4
        )
        ttk.Button(controls, text="Edit / preview", command=self.edit_selected).pack(
            side=tk.RIGHT, padx=4
        )

        table_frame = ttk.Frame(self, padding=(10, 0))
        table_frame.pack(fill=tk.BOTH, expand=True)
        columns = ("safe", "organisation", "type", "role", "email", "area", "reason")
        self.tree = ttk.Treeview(
            table_frame, columns=columns, show="headings", selectmode="extended"
        )
        widths = {
            "safe": 70,
            "organisation": 220,
            "type": 140,
            "role": 160,
            "email": 230,
            "area": 150,
            "reason": 260,
        }
        labels = {
            "safe": "Status",
            "organisation": "Organisation",
            "type": "Type",
            "role": "Published role",
            "email": "Email",
            "area": "Coverage",
            "reason": "Relevance / exclusion",
        }
        for col in columns:
            self.tree.heading(col, text=labels[col])
            self.tree.column(col, width=widths[col], minwidth=60, stretch=col != "safe")
        scrollbar = ttk.Scrollbar(table_frame, orient=tk.VERTICAL, command=self.tree.yview)
        self.tree.configure(yscrollcommand=scrollbar.set)
        self.tree.pack(side=tk.LEFT, fill=tk.BOTH, expand=True)
        scrollbar.pack(side=tk.RIGHT, fill=tk.Y)
        self.tree.tag_configure("held", foreground="#8a3b00")
        self.tree.bind("<Double-1>", lambda _event: self.edit_selected())

        bottom = ttk.Frame(self, padding=10)
        bottom.pack(fill=tk.X)
        ttk.Label(bottom, textvariable=self.status_text).pack(side=tk.LEFT)
        ttk.Button(bottom, text="Open selected as Outlook drafts", command=self.create_drafts).pack(
            side=tk.RIGHT, padx=4
        )
        ttk.Button(bottom, text="SEND selected now", command=self.send_selected).pack(
            side=tk.RIGHT, padx=4
        )
        ttk.Button(bottom, text="Open send log", command=self.open_log).pack(
            side=tk.RIGHT, padx=4
        )

    def browse(self) -> None:
        chosen = filedialog.askopenfilename(
            title="Choose organisation CSV",
            filetypes=[("CSV files", "*.csv"), ("All files", "*.*")],
            initialdir=str(DEFAULT_CSV.parent),
        )
        if chosen:
            self.csv_path.set(chosen)
            self.load_csv()

    def load_csv(self) -> None:
        try:
            self.contacts = load_contacts(Path(self.csv_path.get()))
        except Exception as exc:
            messagebox.showerror(APP_NAME, str(exc))
            return
        self.overrides.clear()
        self.test_sent = False
        self.refresh_tree()
        safe_count = sum(c.safe for c in self.contacts)
        self.status_text.set(
            f"Loaded {len(self.contacts)} contacts: {safe_count} safe for review, "
            f"{len(self.contacts) - safe_count} held."
        )

    def refresh_tree(self) -> None:
        selected_indexes = {
            self.contact_by_item[item].index
            for item in self.tree.selection()
            if item in self.contact_by_item
        }
        self.tree.delete(*self.tree.get_children())
        self.contact_by_item.clear()
        query = self.filter_text.get().strip().lower()
        for contact in self.contacts:
            haystack = " ".join(
                [
                    contact.organisation_name,
                    contact.organisation_type,
                    contact.contact_role,
                    contact.email,
                    contact.coverage,
                    contact.state_code,
                    contact.relevance_reason,
                ]
            ).lower()
            if query and query not in haystack:
                continue
            reason = contact.exclusion_reason or contact.relevance_reason
            item = self.tree.insert(
                "",
                tk.END,
                values=(
                    "Review" if contact.safe else "HELD",
                    contact.organisation_name,
                    contact.organisation_type,
                    contact.contact_role,
                    contact.email,
                    area_for(contact),
                    reason,
                ),
                tags=() if contact.safe else ("held",),
            )
            self.contact_by_item[item] = contact
            if contact.index in selected_indexes:
                self.tree.selection_add(item)

    def selected_contacts(self, *, safe_only: bool = True) -> list[Contact]:
        contacts = [
            self.contact_by_item[item]
            for item in self.tree.selection()
            if item in self.contact_by_item
        ]
        if safe_only:
            unsafe = [c for c in contacts if not c.safe]
            if unsafe:
                messagebox.showwarning(
                    APP_NAME,
                    "Held contacts cannot be drafted or sent:\n\n"
                    + "\n".join(
                        f"• {c.organisation_name}: {c.exclusion_reason}" for c in unsafe
                    ),
                )
            contacts = [c for c in contacts if c.safe]
        return contacts

    def select_first_safe(self) -> None:
        self.tree.selection_remove(self.tree.selection())
        count = 0
        for item, contact in self.contact_by_item.items():
            if contact.safe and count < MAX_LIVE_BATCH:
                self.tree.selection_add(item)
                count += 1
        self.status_text.set(f"Selected {count} safe contacts for review.")

    def clear_selection(self) -> None:
        self.tree.selection_remove(self.tree.selection())
        self.status_text.set("Selection cleared.")

    def edit_selected(self) -> None:
        contacts = self.selected_contacts()
        if len(contacts) != 1:
            messagebox.showinfo(APP_NAME, "Select exactly one safe contact to edit or preview.")
            return
        contact = contacts[0]
        current = self.overrides.get(contact.index, build_message(contact))
        window = tk.Toplevel(self)
        window.title(f"Edit — {contact.organisation_name}")
        window.geometry("900x650")
        ttk.Label(
            window,
            text=f"To: {contact.contact_role} <{contact.email}>  •  Source: {contact.source_url}",
            padding=10,
        ).pack(fill=tk.X)
        ttk.Label(window, text="Subject:", padding=(10, 0)).pack(anchor=tk.W)
        subject = ttk.Entry(window)
        subject.insert(0, current.subject)
        subject.pack(fill=tk.X, padx=10, pady=(0, 8))
        ttk.Label(window, text="Body:", padding=(10, 0)).pack(anchor=tk.W)
        body = tk.Text(window, wrap=tk.WORD)
        body.insert("1.0", current.body_text)
        body.pack(fill=tk.BOTH, expand=True, padx=10, pady=(0, 8))

        def save() -> None:
            self.overrides[contact.index] = MessageCopy(
                subject=subject.get().strip(),
                body_text=body.get("1.0", tk.END).strip(),
            )
            self.status_text.set(f"Saved customised copy for {contact.organisation_name}.")
            window.destroy()

        buttons = ttk.Frame(window, padding=10)
        buttons.pack(fill=tk.X)
        ttk.Button(buttons, text="Save changes", command=save).pack(side=tk.RIGHT)
        ttk.Button(buttons, text="Cancel", command=window.destroy).pack(
            side=tk.RIGHT, padx=6
        )

    def send_test(self) -> None:
        recipient = self.test_recipient.get().strip().lower()
        if not valid_email(recipient):
            messagebox.showerror(APP_NAME, "Enter a valid internal test email address.")
            return
        test_contact = Contact(
            index=0,
            organisation_name="VanAssist internal test",
            organisation_type="other",
            coverage="Internal",
            state_code="",
            website_url=SITE_URL,
            contact_role="Launch review",
            email=recipient,
            source_url=SITE_URL,
            source_checked_at=date.today().isoformat(),
            publication_context="Internal delivery test",
            relevance_reason="Internal test only",
            no_unsolicited_warning=False,
            personal_or_ambiguous=False,
            notes="",
        )
        copy = MessageCopy(
            subject="[TEST] VanAssist organisation outreach sender check",
            body_text=(
                "This is an internal test from the VanAssist Outlook Outreach tool.\n\n"
                f"Expected From address: {SENDER_ADDRESS}\n"
                "Check the actual From address, formatting, links and reply behaviour before "
                "sending any external batch."
            ),
        )
        try:
            outlook = outlook_app()
            mode, detail = sender_status(outlook, SENDER_ADDRESS)
            if mode != "account" and not messagebox.askyesno(
                APP_NAME,
                f"{detail}\n\nSubmit the test anyway to find out whether Exchange "
                "accepts it?",
                icon="warning",
            ):
                log_attempt(test_contact, "internal_test", "cancelled", detail)
                return
            create_outlook_message(outlook, test_contact, copy, display=False, send=True)
            log_attempt(test_contact, "internal_test", "submitted", f"sender_mode={mode}")
            self.test_sent = True
            self.status_text.set(
                f"Internal test submitted to {recipient}. Confirm it arrives from "
                f"{SENDER_ADDRESS} — a delivery failure notice means Send As is not granted."
            )
            messagebox.showinfo(
                APP_NAME,
                "Internal test handed to Outlook.\n\n"
                "Outlook accepting a message is not proof of delivery. Wait for it to "
                f"arrive and confirm the From address is exactly {SENDER_ADDRESS}.\n\n"
                "If you receive a delivery failure notice instead, the signed-in user "
                "does not have Send As permission on the shared mailbox.",
            )
        except Exception as exc:
            log_attempt(test_contact, "internal_test", "failed", str(exc))
            messagebox.showerror(APP_NAME, str(exc))

    def create_drafts(self) -> None:
        contacts = self.selected_contacts()
        if not contacts:
            messagebox.showinfo(APP_NAME, "Select one or more safe contacts first.")
            return
        if len(contacts) > MAX_LIVE_BATCH:
            messagebox.showwarning(
                APP_NAME, f"Select no more than {MAX_LIVE_BATCH} contacts per batch."
            )
            return
        try:
            outlook = outlook_app()
            for contact in contacts:
                copy = self.overrides.get(contact.index, build_message(contact))
                mode = create_outlook_message(
                    outlook, contact, copy, display=False, send=False
                )
                log_attempt(contact, "draft", "created", f"sender_mode={mode}")
            self.status_text.set(f"Created {len(contacts)} drafts in Outlook.")
            messagebox.showinfo(
                APP_NAME,
                f"Created {len(contacts)} unsent Outlook drafts.\n\n"
                "Open Outlook Drafts and review every message before sending.",
            )
        except Exception as exc:
            messagebox.showerror(APP_NAME, str(exc))

    def send_selected(self) -> None:
        contacts = self.selected_contacts()
        if not contacts:
            messagebox.showinfo(APP_NAME, "Select one or more safe contacts first.")
            return
        if len(contacts) > MAX_LIVE_BATCH:
            messagebox.showwarning(
                APP_NAME, f"Live sending is capped at {MAX_LIVE_BATCH} recipients per batch."
            )
            return
        if not self.test_sent:
            messagebox.showwarning(
                APP_NAME,
                "Send and verify the internal test in this session before any external email.",
            )
            return

        outlook = outlook_app()
        mode, detail = sender_status(outlook, SENDER_ADDRESS)
        if mode == "unavailable":
            messagebox.showerror(APP_NAME, detail)
            return
        if mode != "account" and not messagebox.askyesno(
            APP_NAME,
            f"{detail}\n\nDid the internal test actually ARRIVE, showing "
            f"From: {SENDER_ADDRESS}?\n\n"
            "Answer No if you received a delivery failure notice or nothing at all. "
            "Recipients are only reached if Exchange accepts the sender.",
            icon="warning",
        ):
            messagebox.showinfo(
                APP_NAME,
                "External sending stopped.\n\n"
                f"Grant the signed-in user Send As permission on {SENDER_ADDRESS} in the "
                "Microsoft 365 admin centre, restart Outlook, then repeat the internal test.",
            )
            return
        if not messagebox.askyesno(
            APP_NAME,
            f"Send {len(contacts)} individual messages now through Outlook?\n\n"
            f"From: {SENDER_ADDRESS}\n"
            "Each recipient receives a separate personalised email.\n"
            "This cannot be recalled reliably after Outlook accepts it.",
            icon="warning",
        ):
            return

        sent = 0
        failed = 0
        for contact in contacts:
            copy = self.overrides.get(contact.index, build_message(contact))
            try:
                mode = create_outlook_message(
                    outlook, contact, copy, display=False, send=True
                )
                log_attempt(contact, "send", "sent", f"sender_mode={mode}")
                sent += 1
            except Exception as exc:
                log_attempt(contact, "send", "failed", str(exc))
                failed += 1
            self.status_text.set(
                f"Sending: {sent} accepted, {failed} failed, "
                f"{len(contacts) - sent - failed} remaining…"
            )
            self.update_idletasks()
            time.sleep(DEFAULT_DELAY_SECONDS)

        messagebox.showinfo(
            APP_NAME,
            f"Batch complete.\n\nAccepted by Outlook: {sent}\nFailed: {failed}\n\n"
            "Review Sent Items, replies, bounces and opt-outs before another batch.",
        )
        self.status_text.set(f"Batch complete: {sent} accepted, {failed} failed.")

    def open_log(self) -> None:
        LOG_DIR.mkdir(parents=True, exist_ok=True)
        if not LOG_PATH.exists():
            LOG_PATH.write_text(
                "timestamp,action,organisation,email,result,details,source_url\n",
                encoding="utf-8",
            )
        os.startfile(LOG_PATH)  # type: ignore[attr-defined]


def main() -> int:
    if sys.platform != "win32":
        print("This application requires Windows and Classic Outlook.", file=sys.stderr)
        return 1
    if "--self-test" in sys.argv:
        contacts = load_contacts(DEFAULT_CSV)
        safe = [contact for contact in contacts if contact.safe]
        held = [contact for contact in contacts if not contact.safe]
        if len(contacts) != 63 or len(safe) != 62 or len(held) != 1:
            print(
                f"Self-test failed: total={len(contacts)} safe={len(safe)} held={len(held)}",
                file=sys.stderr,
            )
            return 2
        for contact in safe:
            copy = build_message(contact)
            if contact.organisation_name not in copy.subject + copy.body_text:
                print(f"Self-test failed to individualise {contact.email}", file=sys.stderr)
                return 3
        print(
            f"Self-test passed: total={len(contacts)} safe={len(safe)} "
            f"held={len(held)} sender={SENDER_ADDRESS}"
        )
        return 0
    app = MailerGui()
    app.mainloop()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
