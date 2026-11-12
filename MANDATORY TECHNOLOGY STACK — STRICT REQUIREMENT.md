## MANDATORY TECHNOLOGY STACK — STRICT REQUIREMENT

The entire CodeFanz website and application must be built using the following stack:

### Backend
- PHP 8.3+
- Laravel (latest stable version compatible with the server environment).
- MySQL 8.
- Laravel Sanctum for API authentication where appropriate.
- Laravel Queues and Jobs.
- Laravel Scheduler.
- Laravel Events and Notifications.
- RESTful API architecture.

### Frontend
- Laravel Blade Templates.
- Tailwind CSS.
- Pure Vanilla JavaScript (ES6+).
- HTML5.
- CSS3.

### STRICT FRONTEND RESTRICTIONS

DO NOT USE:
- Livewire.
- React.
- Next.js.
- Vue.js.
- Angular.
- Inertia.js.
- Alpine.js.
- jQuery.
- Any frontend framework that introduces unnecessary complexity.

All frontend interactions must be implemented using Vanilla JavaScript.

Use Blade components, layouts, partials, and reusable UI components to maintain clean architecture.

Use Tailwind CSS for styling.

Use JavaScript modules for organized frontend functionality.

### Frontend Architecture

Organize frontend assets into reusable modules:

- `resources/views/layouts/`
- `resources/views/components/`
- `resources/views/pages/`
- `resources/views/admin/`
- `resources/views/customer/`
- `resources/js/modules/`
- `resources/js/services/`
- `resources/js/components/`
- `resources/css/`

Use Laravel Vite for asset compilation and optimization.

### Dynamic Functionality

Use:
- Laravel Controllers.
- Laravel Form Requests.
- Laravel API endpoints.
- Vanilla JavaScript Fetch API.
- AJAX-style asynchronous requests.
- Laravel validation responses.
- CSRF protection.

All dynamic frontend functionality must communicate with Laravel through secure endpoints.

Implement reusable JavaScript utilities for:
- API requests.
- Form submissions.
- Validation.
- Notifications.
- Modal management.
- Dropdowns.
- Data tables.
- Search and filtering.
- Pagination.
- AJAX uploads.
- Dashboard interactions.

### Dashboard Architecture

Build the admin dashboard using Blade and Tailwind CSS with Vanilla JavaScript.

Use reusable Blade components for:
- Sidebar.
- Header.
- Navigation.
- Cards.
- Tables.
- Forms.
- Modals.
- Drawers.
- Charts.
- Notifications.
- Filters.

For charts, use a lightweight charting library only when needed, such as Chart.js. This is permitted because it is a visualization library, not a frontend framework.

### SPA-Like Experience

Where useful, implement partial page updates using Fetch API and History API, without introducing a frontend framework.

However, prioritize:
- Server-side rendering.
- Clean URLs.
- Fast initial page loads.
- SEO-friendly pages.
- Progressive enhancement.
- Accessibility.

Do not turn the entire website into a complex SPA unnecessarily.

### Architecture Rule

Laravel must remain the single source of truth for:
- Business logic.
- Authentication.
- Authorization.
- Validation.
- Database operations.
- Payment processing.
- AI approval workflows.
- MCP integrations.

Vanilla JavaScript must only handle frontend interactions and presentation.

Do not duplicate business logic in JavaScript.

### Final Requirement

The complete public website, admin dashboard, and customer portal must follow this technology stack.

No Livewire, React, Next.js, Vue, Inertia, Alpine.js, or jQuery should be introduced at any stage of development.
