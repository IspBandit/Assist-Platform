"""Unit tests for the pure CSV, safety and copy-building mailer logic."""

from __future__ import annotations

import sys
import unittest
from datetime import date
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / "tools" / "outlook_bulk_mailer"))

from vanassist_outreach_mailer import (  # noqa: E402
    Contact,
    DEFAULT_CSV,
    MAX_LIVE_BATCH,
    SENDER_ADDRESS,
    build_message,
    load_contacts,
    text_to_html,
)


class OutreachMailerTest(unittest.TestCase):
    def test_release_csv_is_safe_by_default_and_racq_is_held(self) -> None:
        contacts = load_contacts(DEFAULT_CSV)
        self.assertEqual(63, len(contacts))
        safe = [contact for contact in contacts if contact.safe]
        held = [contact for contact in contacts if not contact.safe]
        self.assertEqual(62, len(safe))
        self.assertEqual(1, len(held))
        self.assertEqual("RACQ", held[0].organisation_name)
        self.assertEqual("Explicit no-unsolicited warning", held[0].exclusion_reason)

    def test_copy_is_individualised_and_contains_required_boundaries(self) -> None:
        contact = Contact(
            index=1,
            organisation_name="Example Caravan Club",
            organisation_type="club",
            coverage="Queensland",
            state_code="QLD",
            website_url="https://example.test/",
            contact_role="Secretary",
            email="secretary@example.test",
            source_url="https://example.test/contact",
            source_checked_at=date.today().isoformat(),
            publication_context="Published club secretary",
            relevance_reason="Members travel with caravans across regional Queensland",
            no_unsolicited_warning=False,
            personal_or_ambiguous=False,
            notes="",
        )
        message = build_message(contact)
        self.assertIn("Example Caravan Club", message.subject)
        self.assertIn("Hello Secretary at Example Caravan Club", message.body_text)
        self.assertIn(contact.relevance_reason, message.body_text)
        self.assertIn("free service for travellers", message.body_text)
        self.assertIn("not asking for your member list", message.body_text.lower())
        self.assertIn("unsubscribe", message.body_text.lower())
        self.assertIn(SENDER_ADDRESS, message.body_text)

    def test_html_escapes_copy_and_links_site(self) -> None:
        rendered = text_to_html("Hello <team>\n\nhttps://vanassist.com.au/")
        self.assertIn("&lt;team&gt;", rendered)
        self.assertIn('<a href="https://vanassist.com.au/">', rendered)
        self.assertNotIn("Hello <team>", rendered)

    def test_live_batch_remains_capped_at_25(self) -> None:
        self.assertEqual(25, MAX_LIVE_BATCH)


if __name__ == "__main__":
    unittest.main()
