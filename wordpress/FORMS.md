# Website forms

| Form | Page | Fields (required *) | Sends to | Saved |
| --- | --- | --- | --- | --- |
| Contact | `/contact/` | Name*, Callsign, Email*, Subject*, Message*, Consent* | WRA Settings → Send form emails to (info@wi-ra.org) | wp-admin → Messages |
| Volunteer interest | `/resources/#volunteer` | Name*, Callsign, Email*, Areas of interest, Relevant experience, Time to contribute, Comments, Consent* | same | same |

**How it works.** The page posts the form to `/wp-json/wra/v1/message` (the site
script already did this; only the endpoint attribute was added). The plugin
validates it, saves it as a Message, then sends one plain-text email through
`wp_mail()`.

**Email.** From is the site's own sender (set by App Service Email); the visitor
is the **Reply-To**, so a reply goes to them. Subject:
`[WRA website] <Subject> from <Name> (<CALLSIGN>)`. The email ends with a link to
the saved Message.

**Delivery on Azure.** `wp_mail()` is handled by the **App Service Email** plugin
(Azure Communication Services). If messages are saved but show "Email sent: No":

1. Azure portal → the App Service → **Email** (or the Communication Services
   resource): check the email service is connected and the sender domain is verified.
2. If the site is meant to send as `@wi-ra.org`, the domain needs the SPF and DKIM
   records Azure shows for it, added at the DNS host. Ask whoever manages the
   wi-ra.org DNS.
3. Send a test to a Gmail and an Outlook address and check the headers show
   SPF, DKIM and DMARC as pass.

**Spam.** A hidden honeypot field and a 3-second minimum fill time silently drop
bots (they get a "success" reply and nothing is saved or sent). A single address
can send at most 5 messages in 10 minutes.

**Privacy.** Messages are included in Tools → Export Personal Data and Erase
Personal Data. Delete old Messages from the Messages screen as the privacy policy
requires.
