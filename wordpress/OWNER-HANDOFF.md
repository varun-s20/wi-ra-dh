# Running the Wisconsin Repeater Alliance website

A guide for the WRA team. Everything here is done in the WordPress dashboard
at **wi-ra.org/wp-admin**. No coding is needed for any of it.

What you can change yourself:

- **News**: add, edit or remove posts
- **Radio Clubs & Sponsors**: add, edit, hide or remove the clubs and sponsors in the directory
- **ARCS status**: the "Platform status" notice on the ARCS page
- **Website messages**: read what people send through the Contact and Volunteer forms

Everything else (the wording of the pages, the photos, the menus, the footer) is
part of the site design. To change it, send us the change and we will update it.
This is deliberate: it keeps the site looking exactly as approved.

---

## 1. Publish a News post

1. Go to **Posts → Add New Post**.
2. Fill in the post:

| What | Where | Notes |
| --- | --- | --- |
| Title | the large "Add title" line | Shown as the headline on the post and on the News cards |
| Text | the main area | Type normally. Use the **+** button for headings, lists, images and quotes |
| Summary | right-hand panel → **Post** tab → **Excerpt** | One or two sentences. Shown on the home page and News page cards. Leave empty and the opening of the post is used instead |
| Image | right-hand panel → **Featured image** | Shown in the box beside the post and on the cards. A logo or a photo, at least 900 pixels wide. Leave empty and the WRA logo is used |
| Byline | the **Byline** box (right-hand side, or below the text) | Who the post is from, e.g. "Corey Becker, KD9HCW, President". Leave empty for "Wisconsin Repeater Alliance" |
| Category | right-hand panel → **Categories** | Tick **News** |

3. Click **Publish**.

What happens next:

- The post appears straight away as the **first card on the home page** and as the
  **featured post on the News page**. The older ones move down.
- The home page always shows the latest three posts. The News page lists them all.
- To schedule a post for later, click the date next to **Publish** before publishing.

To **edit** a post, go to **Posts**, hover over the title and click **Edit**.
To **remove** one, hover and click **Trash**. It disappears from the site at once,
and you can restore it from **Posts → Trash** for 30 days.

## 2. Add or change a club or sponsor (Radio Clubs & Sponsors)

The Radio Clubs & Sponsors page (wi-ra.org/clubs/, in the **Resources** menu) lists
every club and sponsor automatically, A to Z, all on the same card. You never format
anything: fill in the details and publish. Clubs and sponsors are added the same way;
each one is a "listing".

**Add a club or sponsor**

1. Go to **Clubs & Sponsors → Add New Listing**.
2. Fill in the form, top to bottom:

| What | Where | Notes |
| --- | --- | --- |
| Name | the large "Club or sponsor name" line | Required. Shown on the card exactly as typed, and used for the A to Z order |
| Logo | **Logo** box → **Set logo** | Optional. Upload the logo (or pick one already in the Media Library), then click **Set logo**. A PNG or JPG at least 400 pixels wide, on a white or transparent background. Square and wide logos both fit. No logo? The card shows the standard WRA placeholder |
| Website | **Website address** box | Optional. Type it as you would in a browser, e.g. www.example.org. The card's **Visit website** link opens it in a new tab. No website? The card simply has no link |

3. Click **Publish**. It is on the Radio Clubs & Sponsors page straight away, in its
   A to Z place.

**Change a listing**: go to **Clubs & Sponsors**, click the name, change what you
need, and click **Update**.

**Hide a listing without deleting it**: go to **Clubs & Sponsors**, hover over the
name and click **Hide from directory**. It disappears from the site but stays in the
list, marked **Hidden**. Click **Show in directory** to bring it back. (Inside a
listing, the same thing is **Status: Draft** in the Publish box.) To hide or show
several at once, tick them, choose **Hide from directory** or **Show in directory**
in the **Bulk actions** menu and click **Apply**.

**Remove a listing for good**: hover over its name and click **Trash**. It can be
restored from the Trash for 30 days.

Good to know:

- The order is always A to Z by the name as typed, so "The Example Club" is
  listed under T. Change the name to move it. Visitors can flip the whole directory
  to Z to A with the switch beside the count; that only changes their own view.
- The directory shows **12 listings to a page**. When you add the 13th, page links
  ("1 2 Next") appear under the cards by themselves, and more pages are added as the
  list grows. You never set this up. Hiding or removing listings re-flows the pages.
- A listing without a name cannot go on the site; WordPress saves it as hidden and
  asks for the name.
- If a website address does not look right (for example, it has a space in it),
  WordPress keeps the old address and tells you.

## 3. Update the ARCS status

1. Go to **WRA Settings** in the left-hand menu.
2. Under **ARCS status notice**, change the **Message** and the **"Updated" date**.
3. Click **Save settings**.

The notice on the ARCS page changes immediately. Untick **Show the notice** to hide
it altogether.

## 4. Read messages from the website

Every message sent through the **Contact** form or the **Volunteer** form is:

- **emailed** to the address set in **WRA Settings** (info@wi-ra.org), with the
  sender as the reply-to address, so pressing **Reply** in your email answers them; and
- **saved** under **Messages** in the left-hand menu, so nothing is lost if an email
  goes astray.

In **Messages**, click a message to see everything the person sent. Use **Reply by
email** to answer. If a message shows **Email sent: No**, it was saved but the email
did not go out; reply from there and let us know.

Spam is filtered out automatically and is never saved or emailed.

## 5. Change where form emails go

Administrators only: **WRA Settings → Website forms → Send form emails to**. Use
one address, or several separated by commas. Click **Save settings**.

## 6. When something looks wrong

- **A change does not show up.** Reload the page with Ctrl+F5 (Cmd+Shift+R on a Mac).
- **A post looks different from the others.** Check it has a Summary (Excerpt) and a
  Featured image.
- **Anything else**: send us the page address and a screenshot.

## 7. Please do not change

These control how the site is built. Changing them can break the design:

- **Appearance → Themes**: keep "Wisconsin Repeater Alliance" active.
- **Plugins**: keep **WRA Site** active. It runs the Radio Clubs & Sponsors
  directory, the forms, the ARCS notice and the bylines.
- **Pages**: the site's pages (About, ARCS, Membership, Radio Clubs & Sponsors and
  the rest) are drawn by the design, so editing their text in the page editor has no
  effect on the site. Send us the change instead. Keep the **Radio Clubs & Sponsors**
  page published, at the address /clubs/: the directory is shown on it.
- **Settings → Permalinks**: keep "Post name".
- **Elementor**: still installed, but the site no longer uses it.

If doing something on this list seems necessary, ask us first.
