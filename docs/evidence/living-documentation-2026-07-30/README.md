# Living documentation acceptance evidence

Captured from the repository branch on 2026-07-30 using the maintained
Playwright desktop (1440 × 900) and mobile (390 × 844) projects.

- `documentation-public-desktop-1440x900.png`
- `documentation-public-mobile-390x844.png`

The full acceptance run passed 8/8. Documentation-specific checks cover public
catalogue audience separation, search, all four filters, mobile touch targets,
single-column phone reflow, desktop card layout, article landmarks, visible
keyboard focus and zero page-level horizontal overflow. Unit coverage verifies
the authenticated admin layout renders a route-resolved contextual Help action
and that public catalogue calls cannot retrieve operational guide articles.

No authenticated screenshot credential was introduced solely for evidence.
The contextual admin Help control is validated through layout wiring and route
resolution tests; its destination remains protected by the existing admin role
middleware.
