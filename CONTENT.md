# Editor guide

Everything on the website is edited in the admin panel at `/admin`. Nothing on
the public site is hard-coded, so if you cannot find something here, it usually
means it lives inside a page's section rather than in the layout.

Sign in, then use the left menu:

| Menu | What it is for |
| --- | --- |
| Pages | Every page of the site, in every language |
| Navigation | The links in the header and the footer |
| Media | The image library |
| Messages | Enquiries sent through the contact form |
| Live chat | Conversations started from the bubble on the site |
| Languages | Which languages the site offers |
| Users | Who may sign in |
| Site settings | Company details, colours, animation, social links |

Long lists show a search box first and the filters straight after it — the
filters are ordinary dropdowns, already visible, with no button to open them.

A menu link that points at a page which does not exist is switched **off** and
the row turns grey in **Navigation**. Point it at a real page, or type a real
address, then switch it back on — the public site never shows a dead link.

## Change the text on the home page

1. **Pages** → open the page whose address is `home`.
2. Pick the language tab (English / မြန်မာ / Русский).
3. Under **Page sections**, open the section that holds the text.
4. Each section is a list of *fields*. Open the field, change the value, save.

Fields of a section can be reordered by dragging, and a section can be cloned
with the copy icon — handy when a block of text should repeat.

## Add or remove a section — once, for every language

Sections belong to the page, not to a language. The **main language tab**
(English by default) is where the list of sections lives:

* **Remove text from the whole site**: open the main language tab, press the
  bin on the section, save. It disappears in English, မြန်မာ and Русский at
  once — you never delete the same section three times.
* **Add a section**: same tab, **Add section**. It appears in every language,
  filled with the English words, ready for you to translate in the other tabs.
* **Reorder sections**: drag in the main language tab; the other languages
  follow, each keeping its own words.

The other language tabs therefore only let you change *words*. Their section
list always matches the main language, and translations that still fit are
never thrown away.

## Add a page

1. **Pages** → *New page*.
2. Choose a **page type** (a normal page, a product, news, FAQ, farmer feedback
   or a next project). The type decides the address: products live under
   `/products/…`, news under `/news/…`.
3. Set the **address** (slug), for example `our-story`.
4. Fill in the title and at least one section, then set the status to
   **Published**.
5. Add it to the menu under **Navigation** if visitors should find it.

A page stays invisible until its status is *Published*.

## Listing pages

A **collection** section (news, products, farmer feedback, FAQ) has no content of
its own — it draws whatever is published under that type. Put one on any page and
it stays current: publish a new article and every listing adds it.

`/news` is such a page: a banner plus a news collection. Rename it, restyle it or
delete it like any other page.

## Translate a page

Every page has one tab per active language. The tab is filled from that
language's own content; if a language is empty the site falls back to the
default language, which is why a half-translated page never looks broken.

Press **Translate** at the top of the page to fill the other language tabs with
machine translation. The result waits in the form — read it, correct anything
that reads oddly, then save. Nothing is published until you save.

## Preview before publishing

On the page edit screen press **Preview**. It opens the page as a visitor would
see it, even while the page is still a draft, with a yellow bar at the top to
switch language or go back to the editor.

## Build a section

A section is a layout plus the fields you put in it:

* **Layout** decides the arrangement — a plain stack, a hero, a card grid, text
  beside an image, the animated company profile, the farm-journey timeline, a
  band with a button, or one of the soil infographics.
* **Fields** are the pieces. Common ones:

| Field | Use it for |
| --- | --- |
| Eyebrow | The small label above a title |
| Title / Subtitle | Headings |
| Paragraph / Rich text | Body copy; rich text allows links, lists and bold |
| Image / Gallery / Video | Pictures and embedded video |
| List / Checklist / Stats / Milestones | Repeated rows of short text, ticks, numbers or years |
| Button | A link with a label and a destination |
| Globe | The Russia–Myanmar hero animation caption |

Signature layouts only draw their illustration when the fields they need are
present. Add a `profile`, `mission` and `vision` field and the company-profile
layout appears; if they are missing the section renders as a plain stack
instead of an empty frame.

Body copy is not printed as a wall of text: the first paragraph stays with the
heading as an introduction, and every paragraph or rich-text block after it is
laid out as its own card that fades in as the visitor scrolls. Long pages are
broken into readable pieces without the editor doing anything extra.

## Put a video on a page

Add a **Video** section and paste the address straight from the browser's
address bar — a YouTube watch page, a `youtu.be` share link, a Shorts link or a
full embed address. The player address is worked out from what you pasted, so
none of those shapes has to be converted by hand. Vimeo works the same way.

* **Title** is the caption under the player and the label of the plain link
  shown when the video cannot be embedded.
* **Shape** switches between landscape (16:9) and portrait (9:16, for Shorts and
  phone recordings).
* A single video field can also be dropped inside a section field list.

If a link is not a playable video the field turns red when you save and the
page prints nothing in its place, so a broken embed can never reach a visitor.

## Product photos

Products carry two picture fields on the edit screen:

* **Featured image** — the one photo used in listings and as the product's main
  picture.
* **Product images** — the gallery shown under the main picture. Pick several and
  drag them into the order you want; that order is what the visitor sees. The
  first gallery photo also becomes the product's picture when no featured image
  is set.

## Change colours and animation

**Site settings → Look and feel.** The four colours repaint the whole site
immediately — headings, buttons, cards, footer and illustrations all derive from
them. **Animation** switches between *Dynamic* (full motion), *Calm* (shorter,
quieter transitions) and *None*; **Animation speed** is any CSS duration such as
`900ms`.

## Header and footer links

Both menus are built under **Navigation**: pick *Header* or *Footer* as the
location, then add a row per link. A row is either a page (it keeps working when
the page's address is changed) or a custom address, with a label for each
language.

## Social media icons

**Site settings → Social links** holds one address per network. Filling a field
in adds that network's icon to the footer; an empty field leaves the icon out,
so the footer never shows a link you do not have.

## Farmer feedback cards

A **feedback collection** section has no content of its own — the quotes come
from pages of type *Farmer feedback*. On the section choose:

* **Display** — *carousel* slides the cards, *grid* shows them all at once.
* **Cards on screen** — how many fit side by side (one to three); phones always
  show one.
* **Seconds per slide** — how long each pair stays before the next moves in.
  Hovering over the cards, or tabbing into them, holds them still, and a visitor
  who has switched animations off in their system gets a static list.

Each card shows the quote on top and the farmer's portrait, name, location and
crops along the bottom, with a link through to their full story.

## Choose the home page

**Site settings → Site → Home page** holds the address of the page shown at `/`.
Leave it as `home` unless you rename that page.

## Handle enquiries

**Messages** lists everything sent through the contact form, newest first, with
a new/read state. The form can be closed from **Site settings → Contact form**,
and the address enquiries are delivered to is set there too.

## Answer a live chat

A visitor types into the bubble in the corner of any page and the conversation
appears in **Live chat**, most recent activity at the top. The number on the
menu is how many conversations have something nobody has read yet.

1. Open a conversation — the visitor's name, email and language are shown at the
   top, and opening it marks their messages read.
2. Type in the box at the bottom and press Enter (or **Send**). The visitor sees
   the reply straight away.
3. **End chat** when you are done. The visitor is told in the transcript, with
   the email you can be reached on. Nothing is deleted: if they write again the
   conversation simply reopens, with their new question at the bottom.
4. **Assign to me** on a conversation you have taken, so the rest of the team
   knows somebody is answering it.

The list has one filter, **State**, to show only open or only closed chats.

The bubble itself, and the line it opens with, are switched on and off in
**Site settings → Live chat**. With the bubble switched off, visitors use the
contact form instead.

## Put your location on the map

Add a **Map** section to a page and it embeds Google Maps centred on your
location, with the address and an "Open in Google Maps" link underneath. It
needs **no API key and no billing account** — Google's key-free embed is used —
so it works the moment it is switched on.

The location itself lives in **Site settings → Company**:

- **Google Maps location** — a pair of `latitude, longitude`, or a full address.
  Leave it empty and the company address is used to place the pin.
- **Map zoom** — how close the frame is: District, Neighbourhood, Street or
  Building.
- **Google Maps Place ID** — optional. Give it the ID of your real Google listing
  and the map points at that exact place instead of searching for the text.

A single Map section can also override any of these, which is how a page for one
office can show a different address from the rest of the site without touching
the shared settings. Where nothing is configured, the section prints nothing —
there is never an empty frame on the page.
