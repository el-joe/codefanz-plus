# MASTER DEVELOPMENT PROMPT — CODEFANZ DIGITAL PLATFORM
## Claude Opus 5.5 | Figma MCP | Full-Stack Development | AI Automation | SEO | CRM | MCP Server

You are acting as a complete senior software development team consisting of:

- Principal Software Architect
- Senior UI/UX Designer
- Senior Figma Designer
- Senior Laravel Developer
- Senior Frontend Developer
- Senior Database Engineer
- AI & MCP Engineer
- SEO Specialist
- DevOps & Security Engineer
- QA & Automation Engineer

Your mission is to design, architect, develop, test, and prepare a complete production-ready digital platform for **CodeFanz — codefanz.com**.

This is NOT a simple company landing page.

Build a complete software company ecosystem that combines a professional corporate website, project portfolio, CRM, customer acquisition, project management, payment collection, AI content management, social media management, SEO management, and freelance opportunity monitoring.

The platform must be scalable, modular, secure, multilingual, and ready for real-world production.

---

# PHASE 0 — PROJECT DISCOVERY & ENGINEERING RULES

Before writing any code:

1. Inspect the existing project repository and its structure.
2. Inspect the existing website and domain `codefanz.com` if accessible.
3. Identify the current framework, dependencies, database, server configuration, and existing functionality.
4. Never overwrite or remove existing functionality without understanding its purpose.
5. Create a comprehensive implementation plan.
6. Identify missing integrations, credentials, API access, and technical dependencies.
7. Review the available Figma MCP and Canva MCP connections.
8. Check the latest official documentation for frameworks, APIs, MCP, and integrations before implementation.

Create these initial documents:

- `docs/PROJECT_OVERVIEW.md`
- `docs/SYSTEM_ARCHITECTURE.md`
- `docs/DATABASE_DESIGN.md`
- `docs/IMPLEMENTATION_ROADMAP.md`
- `docs/SECURITY_POLICY.md`
- `docs/AI_APPROVAL_WORKFLOW.md`
- `docs/MCP_DOCUMENTATION.md`
- `docs/DEPLOYMENT_GUIDE.md`

Do not start with random coding.

Think about the entire product first, then implement it in organized phases.

## Development principles

- Production-quality code only.
- SOLID principles.
- Clean Architecture where appropriate.
- Modular and reusable components.
- Strict validation and authorization.
- Secure APIs.
- Database transactions for critical operations.
- Proper logging and error handling.
- No duplicated business logic.
- No hardcoded credentials.
- No fake functionality or placeholder buttons.
- No unnecessary dependencies.
- No destructive database operations without explicit approval.
- Maintain backward compatibility when modifying existing functionality.

---

# PHASE 1 — FIGMA FIRST: COMPLETE DESIGN SYSTEM

IMPORTANT: DO NOT START IMPLEMENTING THE WEBSITE BEFORE COMPLETING THE FIGMA DESIGN.

Use the connected Figma MCP.

First, load and follow the appropriate Figma workflow instructions and inspect the available tools.

Create a professional, complete, high-fidelity Figma Design file for CodeFanz.

The design should look like a premium international software development agency, not a generic template.

## Brand identity

Brand: CodeFanz

Website: https://codefanz.com

Industry: Software Development & Digital Solutions.

Brand positioning:

"Turning Ideas Into Powerful Digital Products."

The visual identity should communicate:

- Innovation.
- Engineering excellence.
- Reliability.
- Technical expertise.
- Modern software development.
- Global business capabilities.

Create a distinctive logo treatment and visual identity. Inspect the existing brand assets first and preserve approved brand elements.

## Design direction

Use a premium modern SaaS / Software Agency visual language.

Requirements:

- Professional typography.
- Excellent spacing and hierarchy.
- Modern layouts.
- Carefully selected colors.
- High-quality visual assets.
- Subtle animations and interactions.
- Professional project presentation.
- Responsive design.
- Excellent mobile experience.
- Accessible contrast and typography.

Do not create an overly complicated interface.

## Figma deliverables

Design all the following screens:

### Public Website

1. Homepage.
2. About Us.
3. Services Overview.
4. Individual Service Details.
5. Technologies & Programming Languages.
6. Projects Portfolio.
7. Project Details.
8. Products / Software Solutions.
9. Product Details.
10. Industries We Serve.
11. Blog Listing.
12. Blog Article.
13. Contact Us.
14. Request a Quote.
15. AI Project Assistant Chat.
16. Careers.
17. Frequently Asked Questions.
18. Testimonials.
19. Privacy Policy.
20. Terms & Conditions.
21. Custom 404 and Error Pages.

### Customer Portal

1. Customer Login.
2. Customer Registration if enabled.
3. Customer Dashboard.
4. My Projects.
5. Project Details.
6. Project Milestones.
7. Invoices.
8. Payment History.
9. Payment Link Checkout.
10. Project Documents.
11. Conversations.
12. Support Tickets.
13. Notifications.
14. Profile and Settings.

### Admin Dashboard

Design a comprehensive admin dashboard covering all system modules described in this prompt.

### AI Management Interface

Design screens for:

- AI Content Studio.
- AI Article Generator.
- SEO Assistant.
- Social Media Publisher.
- AI Suggestions.
- Approval Queue.
- MCP Activity Logs.
- Freelance Projects Radar.
- Automation Management.

## Design system

Create:

- Color variables.
- Typography variables.
- Spacing system.
- Border radius tokens.
- Shadows.
- Buttons.
- Inputs.
- Dropdowns.
- Tables.
- Modals.
- Drawers.
- Cards.
- Badges.
- Alerts.
- Navigation.
- Dashboard components.
- Empty states.
- Loading states.
- Error states.

Create reusable components and variants.

## Design assets

Use Canva MCP for appropriate visual assets if available.

All images must be professionally selected and relevant to their content.

Do not use random stock images.

Use optimized illustrations, software mockups, device mockups, and project-related visuals.

After completing Figma, review the entire design for consistency and completeness.

Only then start development.

---

# PHASE 2 — TECHNICAL ARCHITECTURE

Use the following preferred stack unless the existing repository or a documented technical reason justifies a different choice.

## Backend

- PHP 8.3+.
- Latest stable Laravel version compatible with the environment.
- MySQL 8.
- Laravel Sanctum where appropriate.
- Laravel Queues.
- Redis when available and justified.
- Laravel Scheduler.
- Laravel Events and Notifications.
- RESTful APIs.

## Frontend

Preferred:

- Next.js.
- TypeScript.
- Tailwind CSS.
- Reusable component architecture.
- Server-side rendering where beneficial.
- Modern responsive UI.

Alternatively, use Laravel + Livewire if the existing architecture makes it the more practical choice.

Do not introduce two competing frontend architectures unnecessarily.

## Automation

- n8n for external integrations and workflows.
- Webhooks for event-driven processes.
- Laravel Jobs for internal business processes.
- OpenAI API for AI features.
- A dedicated MCP Server for Claude integration.

## Database

Use MySQL with:

- Proper relationships.
- Foreign keys.
- Indexes.
- Unique constraints.
- Transactions.
- Soft deletes where appropriate.
- Audit trails.
- Database migrations.
- Seeders for essential system data.

Create a database architecture that supports future growth.

---

# PHASE 3 — PUBLIC CORPORATE WEBSITE

Build a complete multilingual corporate website.

Languages:

- English.
- Arabic.

Architecture must support adding more languages later.

Arabic must have proper RTL support, not just translated text.

## Homepage

Create an impressive homepage with:

1. Premium hero section.
2. Strong value proposition.
3. Primary CTA: Start Your Project.
4. Secondary CTA: Explore Our Work.
5. Services overview.
6. Technologies showcase.
7. Featured projects.
8. Featured software products.
9. Industries served.
10. Development process.
11. Why Choose CodeFanz.
12. Statistics and achievements.
13. Customer testimonials.
14. Latest articles.
15. FAQ.
16. Strong contact CTA.
17. Professional footer.

Every section must be manageable from the admin dashboard.

## Services

Create dynamic service management.

Possible categories:

- Web Development.
- Mobile App Development.
- Custom Software Development.
- Enterprise Software.
- SaaS Development.
- E-commerce Development.
- API Development.
- Backend Development.
- Frontend Development.
- AI Integration.
- AI Automation.
- ERP & CRM Systems.
- UI/UX Design.
- Cloud & DevOps.
- Maintenance & Technical Support.
- Digital Transformation.

Each service needs:

- Name.
- Slug.
- Description.
- Long description.
- Icon.
- Cover image.
- Gallery.
- Features.
- Technologies.
- Related projects.
- FAQs.
- SEO metadata.
- Language translations.
- Publishing status.

Support unlimited services and sub-services.

## Technologies

Build a dynamic technology showcase.

Support all programming languages and technology categories, including:

- PHP.
- Python.
- JavaScript.
- TypeScript.
- Java.
- C#.
- C++.
- Go.
- Rust.
- Ruby.
- Swift.
- Kotlin.
- Dart.
- HTML.
- CSS.
- SQL.
- And additional technologies.

Frameworks and platforms:

- Laravel.
- Symfony.
- React.
- Next.js.
- Vue.
- Angular.
- Node.js.
- Express.
- Flutter.
- React Native.
- Django.
- FastAPI.
- .NET.
- Spring Boot.
- WordPress.
- Shopify.
- And others.

Do not imply that every technology has been used in actual delivered projects. Distinguish between supported technologies, expertise, and verified project experience.

---

# PHASE 4 — PROJECT PORTFOLIO & SOFTWARE PRODUCTS

This is one of the most important sections.

Build a premium portfolio system.

## Project categories

Support:

- Websites.
- Web Applications.
- SaaS Platforms.
- Mobile Applications.
- Android Applications.
- iOS Applications.
- Desktop Software.
- Enterprise Systems.
- APIs & Integrations.
- AI Projects.
- Automation Systems.
- E-commerce Platforms.

## Project management fields

Each project should support:

- Project title.
- Slug.
- Short description.
- Full description.
- Client name (optional/private).
- Industry.
- Project category.
- Project type.
- Technologies used.
- Features.
- Challenges.
- Solutions.
- Results.
- Screenshots.
- Video.
- Gallery.
- Live demo URL.
- Android download URL.
- App Store URL.
- GitHub URL (optional).
- Project date.
- Featured status.
- Visibility status.
- SEO settings.
- Arabic and English content.

## Portfolio frontend

Create:

- Advanced category filters.
- Search.
- Featured projects.
- Professional project cards.
- Project detail pages.
- Image galleries.
- Video previews.
- Responsive mockups.
- Related projects.
- Clear CTA to request a similar solution.

## Software Products

Create a separate module for reusable products and software solutions.

Support:

- Product name.
- Description.
- Features.
- Pricing model (if applicable).
- Demo request.
- Screenshots.
- Documentation.
- Product versions.
- Changelog.
- Licensing information.
- Availability.
- Contact sales CTA.

Products must not automatically be treated as client projects.

---

# PHASE 5 — CUSTOMER ACQUISITION & CRM

Build a complete CRM module.

Every visitor should have an easy way to contact CodeFanz.

## Contact channels

- Contact form.
- Project request form.
- AI chatbot.
- WhatsApp link.
- Email.
- Phone.
- Meeting request.

## Project Request Form

Collect:

- Full name.
- Email.
- Phone.
- Country.
- Company name.
- Project type.
- Required platform.
- Project description.
- Main features.
- Required technologies (optional).
- Estimated budget range (optional).
- Expected deadline (optional).
- Existing website URL (optional).
- Attachments.
- Preferred contact method.
- Preferred language.

Support multiple attachments and secure upload validation.

## CRM Leads

Each lead should have:

- Unique ID.
- Source.
- Customer details.
- Project requirements.
- Assigned team member.
- Priority.
- Status.
- Tags.
- Notes.
- Follow-up date.
- Communication history.
- Attachments.
- Estimated value.
- Conversion status.

Lead statuses:

- New.
- Qualified.
- Contacted.
- Meeting Scheduled.
- Proposal Sent.
- Negotiation.
- Won.
- Lost.
- Archived.

Create a Kanban CRM board and a searchable table.

Add automatic notifications when new leads arrive.

---

# PHASE 6 — AI CUSTOMER CHATBOT

Build a simple but professional AI Project Assistant.

The chatbot is intended to collect project requirements and qualify leads.

IMPORTANT:

The chatbot must work without payment.

No checkout, payment requirement, or subscription is required for a visitor to use the chatbot.

## Chatbot capabilities

The chatbot should:

1. Welcome the visitor.
2. Ask what kind of software they need.
3. Understand the customer's business.
4. Ask about required platforms.
5. Ask about key features.
6. Ask whether they have an existing system.
7. Understand the expected timeline.
8. Ask about budget optionally.
9. Collect contact information.
10. Summarize the requirements.
11. Ask the customer to confirm the summary.
12. Create a CRM lead after confirmation.
13. Send the lead to the admin dashboard.
14. Notify the relevant team.

The conversation must feel natural.

Support Arabic and English.

Use an LLM with configurable provider settings.

Provide fallback behavior when the AI provider is unavailable.

## Chatbot safety

- Do not expose internal prompts.
- Do not reveal API keys.
- Validate all user inputs.
- Prevent prompt injection from accessing private CRM records.
- Limit message frequency.
- Implement abuse prevention.
- Store conversations according to privacy settings.
- Never execute financial or administrative actions through the public chatbot.

The chatbot should not make binding pricing or delivery promises.

All estimates must be clearly marked as preliminary.

---

# PHASE 7 — PROJECT MANAGEMENT & CUSTOMER PORTAL

Build a project management module connected to CRM.

Once a lead becomes a client, the admin can create a project and link it to that client.

## Project fields

- Project number.
- Client.
- Project title.
- Description.
- Contract/reference document.
- Total project value.
- Currency.
- Start date.
- Expected delivery date.
- Current status.
- Assigned team.
- Internal notes.
- Client-visible notes.

## Project statuses

- Planning.
- In Progress.
- On Hold.
- Under Review.
- Completed.
- Cancelled.

## Milestones

Each project can have unlimited milestones.

Each milestone should support:

- Title.
- Description.
- Amount.
- Due date.
- Status.
- Deliverables.
- Attachments.
- Internal notes.
- Client-visible notes.
- Approval status.

Example:

Project: E-commerce Platform

- Milestone 1: UI/UX Design.
- Milestone 2: Backend Development.
- Milestone 3: Frontend Development.
- Milestone 4: Payment Integration.
- Milestone 5: Testing and Deployment.

Support milestone dependencies where appropriate.

## Client Portal

Each customer should have secure access to:

- Their projects.
- Project progress.
- Milestones.
- Payment status.
- Invoices.
- Documents.
- Conversations.
- Support tickets.
- Notifications.

Clients must never access another client's records.

---

# PHASE 8 — PAYMENT LINKS & FINANCIAL MANAGEMENT

Build a professional payment collection system.

The system must allow the admin to create payment requests linked to specific projects and milestones.

## Payment link workflow

1. Admin opens a project.
2. Admin selects a milestone or creates a custom payment request.
3. Admin specifies the amount and currency.
4. System creates a unique secure payment link.
5. Admin sends the link to the client.
6. Client opens a professional checkout page.
7. Client completes payment through an enabled gateway.
8. Gateway sends a webhook.
9. Backend verifies payment status directly with the gateway when supported.
10. System updates the payment record.
11. Related milestone payment status is updated.
12. Client and admin receive notifications.
13. A receipt is generated.

## Payment providers

Use a gateway abstraction layer.

Prepare integrations for providers relevant to CodeFanz's target markets, such as:

- Stripe.
- Paymob.
- PayTabs.
- Fawry where applicable.
- Other providers through future adapters.

Do not assume every provider supports every country, currency, or payment method.

Verify current documentation and eligibility before implementation.

## Payment statuses

- Draft.
- Pending.
- Processing.
- Paid.
- Failed.
- Expired.
- Cancelled.
- Refunded.
- Partially Refunded.

## Financial rules

- Never trust payment success based on a browser redirect.
- Verify webhook signatures.
- Prevent duplicate payment processing.
- Use idempotency.
- Keep immutable transaction references.
- Maintain a complete payment audit log.
- Support partial payments.
- Support multiple payments against one milestone.
- Support project-level payment summaries.
- Support multiple currencies.
- Keep gateway fees separate from project revenue.
- Never store raw card information.

## Payment links

Each link should support:

- Expiration.
- Single-use or reusable behavior where supported.
- Optional payment description.
- Project association.
- Milestone association.
- Unique reference.
- Secure token.
- Payment history.

Never allow public visitors to modify payment amounts.

---

# PHASE 9 — BLOG & CONTENT MANAGEMENT SYSTEM

Build a complete professional blogging system.

## Blog features

- Unlimited articles.
- Categories.
- Tags.
- Authors.
- Featured articles.
- Related articles.
- Search.
- Pagination.
- Reading time.
- Table of contents.
- Article images.
- Rich text editor.
- Drafts.
- Scheduled publishing.
- Revisions.
- Version history.
- Arabic and English content.
- Canonical URL.
- SEO metadata.
- Open Graph metadata.
- Social sharing.
- Internal links.

## Editorial workflow

Statuses:

- Draft.
- AI Draft.
- Pending Review.
- Approved.
- Scheduled.
- Published.
- Rejected.
- Archived.

Support content approval before publication.

AI-generated articles must never be automatically published.

## Content quality

Create an editorial checklist:

- Original and useful content.
- Accurate information.
- Correct language.
- Clear headings.
- Search intent alignment.
- Internal linking.
- External source verification where necessary.
- No fabricated claims.
- No keyword stuffing.
- No duplicate content.
- Proper metadata.
- Image optimization.

---

# PHASE 10 — PROFESSIONAL SEO MANAGEMENT

Build SEO as a core system, not an afterthought.

## Technical SEO

Implement:

- Server-side rendering or suitable prerendering.
- Clean URLs.
- Canonical URLs.
- XML sitemap.
- Dynamic sitemap index.
- robots.txt management.
- Correct HTTP status codes.
- Redirect management.
- 404 pages.
- Breadcrumbs.
- Structured data.
- Open Graph.
- Twitter Cards.
- Hreflang.
- Language-specific metadata.
- Pagination SEO.
- Image SEO.
- Internal linking.
- Duplicate content protection.

## Structured data

Implement suitable Schema.org types:

- Organization.
- WebSite.
- WebPage.
- Service.
- SoftwareApplication.
- Article.
- BreadcrumbList.
- FAQPage where eligible and appropriate.
- ContactPage.

Only output structured data that accurately represents visible page content and meets current search engine guidelines.

## Dynamic SEO editor

Each page should have:

- SEO title.
- Meta description.
- Focus keyword.
- Secondary keywords.
- Canonical URL.
- Index/noindex.
- Open Graph title.
- Open Graph description.
- Social image.
- Schema configuration.
- SEO score.
- Optimization suggestions.

## SEO Audit Dashboard

Detect:

- Missing titles.
- Duplicate titles.
- Missing descriptions.
- Duplicate descriptions.
- Broken internal links.
- Broken external links.
- Missing image alt text.
- Large images.
- Missing canonical URLs.
- Redirect chains.
- Orphan pages.
- Thin content.
- Heading hierarchy problems.
- Missing structured data.
- Unindexed pages where indexation data is available.

Build an SEO health dashboard.

Do not claim that an SEO score guarantees rankings.

---

# PHASE 11 — GOOGLE ANALYTICS & SEARCH CONSOLE

Implement integrations with:

- Google Analytics 4.
- Google Search Console.
- Google Tag Manager where needed.

Use official APIs and OAuth 2.0.

## Analytics dashboard

Display:

- Visitors.
- Sessions.
- Page views.
- Traffic sources.
- Top landing pages.
- Conversion events.
- Lead submissions.
- Chatbot conversions.
- Contact form conversions.
- Project inquiry conversions.

## Search Console dashboard

Display available metrics:

- Search clicks.
- Impressions.
- CTR.
- Average position.
- Top queries.
- Top pages.
- Country performance.
- Device performance.
- Search appearance.

Support date filtering and comparisons.

Store OAuth tokens securely.

Handle token expiration and reconnection.

Never ask the user to expose credentials in the source code.

---

# PHASE 12 — AI CONTENT & SOCIAL MEDIA DEPARTMENT

Build a dedicated AI-powered marketing management module.

This should be a complete internal marketing department.

## AI Content Studio

Allow the admin to generate:

- Blog articles.
- SEO landing page drafts.
- Service descriptions.
- Project descriptions.
- Social media captions.
- Marketing campaigns.
- Product announcements.
- Case studies.
- Email marketing drafts.
- FAQs.
- Website content improvements.

Support Arabic and English.

Support configurable AI providers:

- OpenAI first.
- Future providers through an adapter architecture.

Allow configuration of models, prompts, limits, and usage costs.

## Social Media Management

Prepare integrations for:

- Facebook Pages.
- Instagram Professional Accounts.
- LinkedIn.
- X.
- TikTok.
- YouTube.
- Pinterest where API access permits.

Use official APIs and their supported permissions.

Do not assume every platform allows every publishing operation.

## Social publishing workflow

1. AI generates a post.
2. Admin reviews the content.
3. Admin edits or rejects it.
4. Admin approves it.
5. Post enters the publishing queue.
6. System publishes through an authorized API.
7. System stores the published URL and platform response.
8. System tracks supported engagement metrics.

Support:

- Multiple social accounts.
- Platform-specific content.
- Media attachments.
- Post scheduling.
- Content calendar.
- Drafts.
- Approval.
- Publishing history.
- Failed publishing retries.
- Analytics.

If a platform does not permit direct publishing, provide an export or manual publishing workflow.

Never simulate a successful publication.

---

# PHASE 13 — CRITICAL: AI APPROVAL & GOVERNANCE SYSTEM

This is a mandatory core requirement.

ANY action that creates or modifies public-facing website content or social media content through AI or MCP must go through human approval.

## Approval-required actions

Examples:

- Creating blog articles.
- Editing published articles.
- Creating services.
- Editing service descriptions.
- Creating portfolio projects.
- Editing project content.
- Changing website page content.
- Changing SEO metadata.
- Creating redirects.
- Changing homepage sections.
- Creating social media posts.
- Editing social media posts.
- Publishing social media posts.
- Scheduling posts.
- Modifying website design settings.
- Uploading public media.
- Modifying navigation or menus.

## Approval workflow

Use the following states:

- Pending.
- Under Review.
- Approved.
- Rejected.
- Changes Requested.
- Applied.
- Failed.

The workflow:

1. AI proposes an action.
2. System creates a pending change request.
3. System generates a before/after preview.
4. Admin receives a notification.
5. Admin reviews the proposal.
6. Admin approves, rejects, or requests changes.
7. Only an approved action can be executed.
8. System records the result.
9. Admin can inspect the full audit history.

## Important distinction

Approval must be checked on the backend.

Never rely on frontend buttons or client-side flags to authorize an operation.

## Emergency controls

Provide:

- Pause all AI automation.
- Disable social publishing.
- Disable MCP write operations.
- Revoke individual integrations.
- Cancel scheduled content.
- View all pending changes.

## Exceptions

Read-only operations can run without approval.

Private draft generation can be allowed without publication approval.

Financial operations, payment refunds, user deletion, permission changes, and security configuration changes must have their own explicit authorization workflows.

No AI agent may independently initiate refunds, transfer money, change payment credentials, or grant itself permissions.

---

# PHASE 14 — CODEFANZ FREELANCE PROJECTS RADAR

Build a complete independent module within the platform.

Name: Freelance Projects Radar.

Purpose: Monitor freelance platforms and identify new projects matching CodeFanz's skills and business interests.

Initially focus on:

- Mostaql.
- Upwork.
- Khamsat.
- Freelancer.
- PeoplePerHour.

Allow administrators to add future platforms.

## Data collection architecture

Create an independent connector for each platform.

Possible collection methods:

- Official APIs.
- Supported RSS feeds.
- Saved-search email notifications.
- Gmail or IMAP integrations.
- Permitted web data sources.
- Compliant browser-based collection when explicitly allowed.

Respect platform terms, rate limits, authentication restrictions, and anti-bot protections.

Do not bypass CAPTCHAs, access controls, or authentication.

If a platform does not provide a permitted integration, display its connector status as unavailable and offer supported alternatives.

## Project storage

Each collected project should include:

- Platform.
- External project ID.
- Title.
- Description.
- URL.
- Budget.
- Currency.
- Project type.
- Skills.
- Publication date.
- Client information when publicly available.
- Number of proposals when available.
- Collection timestamp.
- Source data.
- AI evaluation.
- Status.

Use unique constraints to prevent duplicate projects.

## Smart filters

Support multiple saved filter profiles.

Examples:

- Laravel projects.
- Flutter projects.
- Full-stack projects.
- SaaS projects.
- API development.
- ERP and POS systems.
- AI integration.
- Projects with specific budget ranges.
- Projects posted recently.
- Projects with low proposal counts.

Support:

- Include keywords.
- Exclude keywords.
- Required skills.
- Optional skills.
- Budget ranges.
- Currency.
- Project type.
- Platforms.
- Date ranges.
- Minimum AI score.
- Languages.
- Client preferences.

## AI matching

Create a configurable scoring engine from 0 to 100.

Evaluate:

- Technical skill compatibility.
- Experience compatibility.
- Scope clarity.
- Budget suitability.
- Business opportunity.
- Estimated implementation complexity.
- Potential profitability.
- Competition where data is available.

Show explanations for each score.

Never automatically discard projects just because of imperfect keyword matches.

## Notifications

Integrate Telegram Bot.

Each notification should include:

- Project title.
- Platform.
- Budget when available.
- Posted date.
- Matching skills.
- AI score.
- Why it matches.
- Direct project URL.

Notification levels:

- 90–100: Immediate alert.
- 75–89: Normal alert.
- 60–74: Daily digest.
- Below 60: Save without notification.

All thresholds must be configurable.

Prevent repeated alerts for unchanged projects.

## Opportunity analytics

Build reports showing:

- New projects per platform.
- Most requested technologies.
- Budget distributions.
- Best matching categories.
- Opportunities over time.
- Projects marked Interested.
- Projects marked Applied.
- Won and Lost opportunities.

Add an optional AI proposal generator that creates a tailored proposal draft based on the project description and approved company profile.

Do not submit proposals automatically.

---

# PHASE 15 — CODEFANZ MCP SERVER

Build a dedicated MCP Server for CodeFanz.

The goal is to let Claude Code or Claude Desktop interact with the website and its administrative system through controlled MCP tools.

Prefer TypeScript and the official MCP SDK.

Use the latest stable official MCP documentation.

## MCP architecture

Create a standalone MCP server with:

- Secure authentication.
- Scoped permissions.
- Tool definitions.
- Input schemas.
- Output schemas.
- Error handling.
- Audit logging.
- Approval integration.
- Rate limiting.
- Documentation.
- Automated tests.

Support Streamable HTTP transport for remote integrations where appropriate.

Provide local development support through stdio if practical.

Never expose an unrestricted database connection to MCP.

MCP tools must call authenticated backend services.

## MCP tools — READ OPERATIONS

Claude should be able to:

- List website pages.
- Read page content.
- Read services.
- Read portfolio projects.
- Read software products.
- Read blog articles.
- Read SEO metadata.
- Read analytics summaries.
- Read Search Console reports.
- Read social media accounts.
- Read social publishing history.
- Read pending approvals.
- Read project and lead summaries according to permissions.
- Read AI content drafts.
- Read website health reports.

Read operations should respect user permissions and data privacy.

## MCP tools — DRAFT CREATION

Claude should be able to:

- Create a blog article draft.
- Create an SEO content draft.
- Propose a service.
- Propose a portfolio project.
- Generate social media drafts.
- Suggest page content improvements.
- Suggest metadata improvements.
- Generate internal linking recommendations.
- Create an SEO audit report.
- Propose website content modifications.

These operations must create pending review items.

They must not directly publish public content.

## MCP tools — APPROVAL OPERATIONS

Provide carefully scoped operations for:

- Listing pending approvals.
- Reading approval details.
- Approving a pending proposal.
- Rejecting a proposal.
- Requesting changes.

Approval operations must require authenticated administrator authorization and must be auditable.

## MCP tools — EXECUTION

Provide narrowly scoped tools to execute already-approved operations.

For example:

- Apply an approved blog draft.
- Publish an approved social post.
- Apply approved SEO metadata.
- Apply an approved service update.

Before execution, verify:

1. The approval exists.
2. It is still valid.
3. The content has not changed since approval.
4. The approver had sufficient permission.
5. The action is within the permitted scope.

Use version checks to prevent applying stale approvals.

## MCP security

- OAuth or secure token authentication.
- Short-lived tokens where appropriate.
- Least privilege.
- Separate read and write scopes.
- Token revocation.
- Audit trails.
- Request validation.
- Rate limits.
- Prompt injection protection.
- No unrestricted shell execution.
- No arbitrary SQL execution.
- No arbitrary filesystem access.
- No arbitrary URL fetching.
- No exposing secrets to model responses.

Create a comprehensive MCP README and connection examples for Claude Code and Claude Desktop.

---

# PHASE 16 — MEDIA MANAGEMENT & AUTOMATIC IMAGE OPTIMIZATION

Build a centralized media management system.

Every image uploaded through the website or generated content workflow must pass through an image optimization pipeline.

## Required formats

- AVIF as the preferred delivery format.
- WebP as a fallback when necessary.
- Preserve original source files when required for future editing.

## Image processing

Use an appropriate image-processing library such as Imagick or libvips.

Requirements:

- Convert supported images to AVIF.
- Generate responsive sizes.
- Generate thumbnails.
- Compress intelligently.
- Preserve aspect ratio.
- Strip unnecessary metadata.
- Preserve color profiles where appropriate.
- Generate optimized variants.
- Validate actual output format.
- Handle transparent images.
- Handle large images asynchronously.
- Reject malformed files.
- Enforce upload limits.

Do not convert SVG or animated formats blindly.

Keep original uploads in a controlled storage area.

## Frontend

- Use responsive images.
- Use lazy loading.
- Use width and height attributes.
- Use appropriate image sizes.
- Prevent layout shifts.
- Use CDN delivery when configured.

## Admin Media Library

Provide:

- Search.
- Categories.
- Filters.
- Preview.
- Alt text.
- File dimensions.
- File size.
- Optimized format.
- Usage references.
- Replacement workflow.
- Unused asset detection.

AI-generated images must also follow the media optimization pipeline.

---

# PHASE 17 — AUTOMATION & N8N INTEGRATION

Build an automation integration layer.

n8n should handle suitable external workflows while Laravel remains the source of truth for business data and authorization.

Potential workflows:

1. New lead notification.
2. New project request.
3. New chatbot lead.
4. Payment confirmation.
5. Milestone update.
6. Customer follow-up.
7. Blog approval notification.
8. Social publishing notification.
9. Freelance project collection.
10. AI matching.
11. Daily opportunity digest.
12. Weekly SEO report.
13. Broken-link alerts.
14. Analytics summaries.
15. Content calendar reminders.

Provide signed webhooks.

Use retries and idempotency.

Record failed workflow executions.

Allow administrators to enable or disable individual integrations.

Do not make critical financial operations dependent on an unverified n8n execution.

---

# PHASE 18 — ADMIN DASHBOARD

Create a comprehensive central admin panel.

## Dashboard modules

1. Overview.
2. Pages.
3. Navigation & Menus.
4. Services.
5. Technologies.
6. Portfolio.
7. Software Products.
8. Leads & CRM.
9. Customers.
10. Projects.
11. Milestones.
12. Payments.
13. Invoices.
14. Transactions.
15. Blog.
16. Categories & Tags.
17. Media Library.
18. SEO Management.
19. SEO Audits.
20. Google Analytics.
21. Search Console.
22. Social Accounts.
23. Social Media Calendar.
24. AI Content Studio.
25. AI Approval Queue.
26. MCP Management.
27. Freelance Projects Radar.
28. n8n Integrations.
29. Notifications.
30. Support Tickets.
31. Users & Roles.
32. Audit Logs.
33. System Settings.

## Roles and permissions

Use granular permissions.

Suggested roles:

- Super Admin.
- Administrator.
- Project Manager.
- Sales Manager.
- Content Manager.
- SEO Specialist.
- Social Media Manager.
- Developer.
- Finance Manager.
- Support Agent.
- Client.

A user may have multiple roles.

Implement policies and permission checks on the backend.

## Settings

Make business-related settings configurable:

- Company information.
- Logo.
- Contact information.
- Social links.
- Languages.
- Currencies.
- Timezone.
- Email settings.
- Payment providers.
- AI providers.
- API credentials.
- Analytics connections.
- SEO defaults.
- Notification preferences.
- Media processing settings.
- MCP permissions.
- Approval rules.

Sensitive credentials must be encrypted at rest.

---

# PHASE 19 — PERFORMANCE, SECURITY & QUALITY

## Performance

Target excellent Core Web Vitals.

Optimize:

- LCP.
- INP.
- CLS.
- JavaScript bundles.
- CSS.
- Images.
- Database queries.
- Caching.
- API responses.
- Background jobs.

Use appropriate caching invalidation when content changes.

Do not cache private customer data publicly.

## Security

Implement:

- CSRF protection.
- XSS prevention.
- SQL injection prevention.
- SSRF protection.
- Secure session handling.
- Authentication throttling.
- MFA for privileged accounts.
- Secure password reset.
- Authorization policies.
- Secure file uploads.
- Signed webhooks.
- API rate limiting.
- Audit logging.
- Backup procedures.
- Secret management.
- Data retention policies.

Apply OWASP security practices.

## Privacy

Provide:

- Privacy Policy.
- Cookie consent where applicable.
- Contact data handling.
- Consent records.
- Data export.
- Data deletion procedures where legally applicable.
- Secure customer document storage.

## Quality assurance

Write tests for:

- Authentication.
- Authorization.
- CRM.
- Project creation.
- Milestone calculations.
- Payment verification.
- Duplicate webhooks.
- AI approval workflows.
- MCP authorization.
- Blog publication.
- SEO metadata.
- Media conversion.
- Social publishing.
- Client data isolation.

Include integration tests and browser-based end-to-end tests for critical flows.

---

# PHASE 20 — DEPLOYMENT & PRODUCTION READINESS

Prepare the project for deployment on a Linux VPS.

Prefer a conventional deployment using:

- Ubuntu.
- Nginx.
- PHP-FPM.
- MySQL.
- Supervisor.
- Redis if needed.
- SSL.
- Cron / Laravel Scheduler.
- Queue workers.

Docker is optional, not mandatory.

Create:

- Production environment example.
- Nginx configuration.
- Queue worker configuration.
- Scheduler configuration.
- Deployment scripts.
- Database backup instructions.
- Restore instructions.
- Log rotation.
- Health checks.
- Monitoring instructions.
- SSL setup documentation.

Never put production credentials in Git.

Do not automatically deploy or overwrite production.

Require explicit approval before destructive deployment actions or migrations that could cause data loss.

## Production checklist

Before declaring the project ready:

- All critical pages work.
- Mobile layouts work.
- Arabic RTL works.
- Forms work.
- Emails work.
- Chatbot works without payment.
- CRM works.
- Projects and milestones work.
- Payment verification works in sandbox.
- Media conversion works.
- SEO metadata is correct.
- Sitemap works.
- Robots.txt is correct.
- Analytics integration works when credentials are configured.
- Search Console integration works when authorized.
- MCP read operations work.
- MCP write approval works.
- Social publishing works in authorized test environments.
- Tests pass.
- No critical security issues remain.
- Backups are documented.

---

# PHASE 21 — REQUIRED DEVELOPMENT EXECUTION STRATEGY

Do not attempt to generate the entire system in one uncontrolled coding operation.

Work in structured milestones.

## Milestone 1 — Discovery and Architecture

- Inspect repository.
- Review existing infrastructure.
- Prepare technical documentation.
- Design database architecture.
- Define modules and dependencies.

## Milestone 2 — Figma Design

- Create complete design system.
- Design public website.
- Design admin dashboard.
- Design customer portal.
- Design AI and automation screens.
- Review responsiveness and consistency.

## Milestone 3 — Core Platform

- Authentication.
- Roles and permissions.
- Admin foundation.
- Multilingual support.
- CMS.
- Media management.

## Milestone 4 — Corporate Website

- Homepage.
- Services.
- Technologies.
- Portfolio.
- Software products.
- Contact pages.

## Milestone 5 — CRM & Customer Acquisition

- Lead management.
- Project request forms.
- AI chatbot.
- Customer portal.

## Milestone 6 — Project Management & Payments

- Projects.
- Milestones.
- Invoices.
- Payment links.
- Gateway integrations.

## Milestone 7 — Blog & SEO

- CMS blogging.
- SEO tools.
- Technical SEO.
- Google Analytics.
- Search Console.

## Milestone 8 — AI Marketing Department

- AI content generation.
- Social media integrations.
- Content calendar.
- Approval workflow.

## Milestone 9 — MCP Server

- MCP tools.
- Authentication.
- Approval integration.
- Claude connection.
- Documentation.

## Milestone 10 — Freelance Radar

- Platform connectors.
- Collection pipeline.
- Smart filters.
- AI matching.
- Telegram bot.
- Analytics.

## Milestone 11 — Testing & Optimization

- Security.
- Performance.
- Automated tests.
- Accessibility.
- SEO validation.

## Milestone 12 — Production Preparation

- Deployment documentation.
- Environment configuration.
- Backups.
- Monitoring.
- Final QA.

---

# PHASE 22 — FINAL ACCEPTANCE CRITERIA

The project is not complete merely because the homepage looks good.

The final deliverable must contain:

1. Complete Figma design file.
2. Working public corporate website.
3. Working admin dashboard.
4. Working customer portal.
5. Complete CMS.
6. Project portfolio management.
7. CRM and lead management.
8. AI customer chatbot without payment.
9. Project management and milestones.
10. Project-linked payment links.
11. Financial management.
12. Blog management.
13. Technical SEO and SEO dashboard.
14. Google Analytics integration.
15. Search Console integration.
16. AI content generation.
17. Social media management.
18. Mandatory human approval system.
19. Working CodeFanz MCP Server.
20. Freelance Projects Radar.
21. Telegram notifications.
22. Automatic AVIF image optimization.
23. n8n integration.
24. Security and automated tests.
25. Production deployment documentation.

Every feature must be implemented or explicitly documented as requiring external credentials, permissions, or unavailable platform API access.

Never mark an integration as complete just because its settings page exists.

---

# FINAL INSTRUCTIONS — HOW YOU SHOULD WORK

Act as an autonomous senior engineering team, but follow controlled execution.

1. Begin by inspecting the existing project.
2. Prepare the architecture and roadmap.
3. Create the Figma design FIRST.
4. Review and validate the Figma screens.
5. Start implementation only after the design is ready.
6. Work milestone by milestone.
7. After each milestone, run relevant tests.
8. Review your own implementation.
9. Fix discovered issues before proceeding.
10. Update documentation continuously.
11. Keep track of completed and pending features.
12. Never fake successful API integrations.
13. Never use placeholder content as final production content.
14. Never bypass platform restrictions.
15. Never publish AI-generated content without human approval.

## CRITICAL RULE: HUMAN APPROVAL FOR ALL AI-CREATED PUBLIC CONTENT

Any content generated, edited, or proposed by Claude, an AI agent, or MCP must be reviewed through the CodeFanz approval system before affecting the live website or being published to social media.

Claude can research, analyze, generate, edit drafts, and propose improvements autonomously.

Claude cannot publish or apply changes to live public content without explicit authorized approval.

All operations must be logged with:

- User or AI agent identity.
- Operation.
- Target resource.
- Before state.
- Proposed state.
- Approval identity.
- Execution result.
- Timestamp.

## YOUR FIRST RESPONSE

Do not start generating random source files immediately.

First:

1. Inspect the existing project and available MCP integrations.
2. Report your understanding of the current environment.
3. Present the proposed architecture.
4. Present the Figma design plan.
5. Present the implementation roadmap.
6. Identify external credentials and API permissions that will eventually be required.

Then begin Phase 1 and Phase 2 of the execution plan, with Figma design taking priority over website implementation.

Your goal is to deliver a complete, maintainable, scalable, secure, and genuinely production-ready CodeFanz digital platform — not just a visually attractive website.

**Build it as a real software product that CodeFanz can operate, grow, and eventually expand into additional services.**
