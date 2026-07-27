# WB Listora Customer Journey Book

A customer journey is the documented path a real person takes from "I have a problem" through "I have a result" - the screens they see, the decisions they make, the emails they get, the moments where they choose to keep going or walk away. We write these so anyone on the team (product, marketing, support, engineering) can talk about Listora using the same map. When you ship a feature, you change a journey. When you fix a bug, you fix a journey. When you write a help article, you anchor it to a journey stage.

## Personas (5)

The full cast of people who interact with a WB Listora directory. Each has their own end-to-end journey doc.

| Persona | One-line description | Journey |
|---|---|---|
| **Site Owner** | The directory operator - chooses the niche, runs the install, sets the rules, takes the money | [site-owner.md](site-owner.md) |
| **Agency / Reseller** | Developer-fluent builder shipping a directory product to a client, often white-labeled | covered inside [site-owner.md](site-owner.md) (agency persona shares the operator journey + white-label / REST notes) |
| **Listing Owner** | Local business owner who wants to be found, manages their own listing from a frontend dashboard | [listing-owner.md](listing-owner.md) |
| **Visitor** | The end customer - lands from Google, needs an answer fast, leaves in 10 seconds if nothing fits | [visitor.md](visitor.md) |
| **Moderator** | Trusted team member with capped permissions - approves / rejects / hides content, nothing else | [moderator.md](moderator.md) |

Full persona profiles with goals, pain points, and content tone live in [persona-profiles.md](../marketing/07-brand-assets/persona-profiles.md).

## Cross-cutting journeys

Where the persona journeys answer "what does Sarah do?", these answer "what happens across the whole product?" Use them when you need the macro view or a specific task-shaped slice.

| Journey | Use when |
|---|---|
| [**lifecycle.md**](lifecycle.md) | You need the full Awareness → Advocacy arc - the marketing-and-success view that ties all 5 personas together |
| [**task-based-journeys.md**](task-based-journeys.md) | A real person is asking "how do I do X?" - 10 specific tasks across vendor / visitor / operator with step-by-step paths |
| [**journey-book.md**](journey-book.md) | The bound atlas you hand to a new product manager or marketing director on day 1 - personas + lifecycle + tasks + the cross-persona orchestration map |

## How to read these journeys

Every persona journey uses the same skeleton so you can scan one and predict the shape of the next.

| Section | What it answers |
|---|---|
| **Who this is for** | The 3-5 real-world variants this persona covers (operator, agency, lead-gen owner …) |
| **Stage N - title (duration)** | One numbered stage per major leap in the customer's relationship with the product. Title says what changed. Duration anchors expectations |
| **What you expect** | The customer's mental model - what they think this stage should feel like |
| **What you do / experience** | The actual click-by-click or step-by-step. Specific surfaces (block names, REST routes, email events) |
| **What you do NOT have to do** | The friction Listora removes. Defines the product's value proposition by negation |
| **Common pitfalls** | The thing that breaks for real customers + the exact fix |
| **Related** | Cross-links to other journeys + feature docs so the reader doesn't dead-end |

Sub-heading legend used throughout:

- **What you do** - active steps the customer takes
- **What you experience** - the system's response, the email that arrives, the badge that appears
- **What you expect** - the contract the customer brings to this stage. If we miss it, they leave

## Cross-reference matrix: which features each persona touches

A single feature usually shows up in 2-3 persona journeys. This grid is the map.

| Feature | Site Owner | Agency | Listing Owner | Visitor | Moderator |
|---|:---:|:---:|:---:|:---:|:---:|
| Setup wizard (6 steps) | full | full | - | - | - |
| Listing types + custom fields | full | full | configures own listing | - | - |
| Frontend submission wizard | configures | configures | full | - | - |
| Frontend dashboard (`/my-dashboard/`, `/my-listings/`) | configures | configures | full | - | - |
| Business claims | configures | configures | claim own listing | - | approves claims |
| Search + facets | configures | configures + REST | reviews via "Saved Searches" | full | - |
| Reviews + helpful votes | configures + moderates | configures | replies to own | reads + writes | approves |
| Listing detail page | designs | designs / templates | views own listing | full | spot-checks |
| Listing renewal (manual via REST) | configures expiry window | configures | renews own | - | - |
| Verification badges (Pro) | grants | grants | earns | trusts | - |
| Lead Forms (Pro) | configures | configures + integrates | receives leads | submits | - |
| Pricing plans + credits (Pro) | configures + earns | configures | pays for plan | - | - |
| Needs marketplace (Pro) | enables | enables | responds to needs | posts needs | - |
| Compare listings (Pro) | enables | enables | - | full | - |
| Saved searches (Pro) | enables | enables | - | full | - |
| Moderators feature (Pro) | grants caps | grants caps | - | - | full |
| Audit Log (Pro) | reads | reads | - | - | - |
| Email Log + notifications | configures | configures | receives | receives review-reply email | receives assignment email |
| Anti-spam (6-layer) | configures CAPTCHA + Akismet | configures | benefits | benefits | benefits |
| Migration (CSV / JSON / GeoJSON / 4 competitors) | runs | runs | - | - | - |
| Analytics (Pro) | reads | reads | reads own | - | - |
| White-label (Pro) | - | full | - | - | - |
| REST API (58 Free + 73 Pro) | - | full (headless / mobile) | - | - | - |
| Template overrides | designs | full | - | - | - |
| 259 hooks | - | full | - | - | - |

Legend: **full** = primary user of the feature - **configures** = sets it up but doesn't use it daily - **-** = doesn't touch this feature.

## When to update these docs

Anchor every customer-visible change to a journey:

- New feature ships → add a stage or sub-section to the persona(s) it serves + a task entry to `task-based-journeys.md` if it has a discrete "I want to X" shape
- Bug fix that changes a flow → update the affected stage so the doc matches reality
- Pricing or packaging change → revise `lifecycle.md` (Purchase + Activation stages) and `journey-book.md` cross-persona map
- New persona surfaces (e.g. "API consumer", "translator") → add a profile to [persona-profiles.md](../marketing/07-brand-assets/persona-profiles.md) and either fold into an existing journey or create a new one here

The journey docs are the product's narrative source of truth. Keep them green.
