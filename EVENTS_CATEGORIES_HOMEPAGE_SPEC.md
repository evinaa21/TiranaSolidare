# Events, Categories, and Homepage Specification

## Goal

This file defines the next round of work for events and categories after the database cleanup. The main objective is to make categories fully admin-managed, visually richer on the homepage, and easier to scale when the number of categories grows.

## Current Problems

1. Homepage category cards in [public/index.php](public/index.php) use a hardcoded PHP metadata map for images, descriptions, and icons instead of database-driven content.
2. Categories currently store only a single field: `emri`.
3. Admins can create, rename, and delete categories, but they cannot upload a category banner/image at creation time.
4. The homepage currently renders all categories in one grid, which does not scale well when category count grows.
5. Category content shown on the homepage is not tied to the admin who created or last updated the category.

## Product Requirements

### Category Management

Admins should be able to create categories with:

1. Category name
2. Banner/image uploaded by the admin
3. Short description
4. Optional sort order
5. Active/inactive visibility toggle

Each category should also keep track of:

1. Which admin created it
2. Which admin last updated it
3. Created timestamp
4. Updated timestamp

### Homepage Categories Section

The homepage category section should:

1. Pull banner, description, and display data from the database instead of hardcoded arrays
2. Show only a limited number of categories initially
3. Add a button below the category grid such as `Shiko më shumë` / `Shiko më shumë kategori`
4. Expand the category list or navigate to a full category listing page
5. Keep category cards clickable and route users into filtered event pages

### Event Relationship

Events should continue to belong to categories, but the homepage should present categories as editorial content blocks, not just raw names with event counts.

## Proposed Database Changes

### Table: `Kategoria`

Add these fields:

1. `slug` VARCHAR(120) NOT NULL UNIQUE
2. `pershkrimi_shkurter` VARCHAR(255) NULL
3. `banner_path` VARCHAR(500) NULL
4. `sort_order` INT NOT NULL DEFAULT 0
5. `is_active` TINYINT(1) NOT NULL DEFAULT 1
6. `created_by_admin_id` INT NULL
7. `updated_by_admin_id` INT NULL
8. `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
9. `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP

### Foreign Keys

Recommended foreign keys:

1. `created_by_admin_id` -> `Perdoruesi.id_perdoruesi`
2. `updated_by_admin_id` -> `Perdoruesi.id_perdoruesi`

### Migration Notes

If old categories exist in the future, populate:

1. `slug` from `emri`
2. `pershkrimi_shkurter` with NULL or a generated placeholder
3. `sort_order` by current alphabetical order or current homepage order
4. `is_active = 1`

## Backend/API Changes

### Categories API: [api/categories.php](api/categories.php)

Extend category endpoints as follows:

1. `GET action=list`
Return full category display data for admin use and homepage use.

2. `POST action=create`
Accept:
   - `emri`
   - `pershkrimi_shkurter`
   - `banner_path`
   - `sort_order`
   - `is_active`

3. `PUT action=update&id=<id>`
Allow updating the same fields plus tracking `updated_by_admin_id`.

4. `DELETE action=delete&id=<id>`
Keep current behavior or consider soft-delete via `is_active = 0` if categories need history.

5. Optional new endpoint: `GET action=homepage`
Return only homepage-visible categories in the order needed for the landing page, including event counts.

### Upload Handling

Use the existing upload infrastructure in [api/upload.php](api/upload.php) for category banners as well.

Recommended approach:

1. Admin selects image during category creation
2. Frontend uploads it first
3. API returns stored path/URL
4. Category create request stores that URL in `banner_path`

Validation rules:

1. Accept JPEG, PNG, WEBP, GIF if the current upload pipeline already allows them
2. Enforce file size limit
3. Enforce image dimensions or minimum aspect ratio if needed
4. Provide fallback image when no banner exists

## Admin Dashboard Changes

### Category Create Form

In [views/dashboard.php](views/dashboard.php), expand the current category create panel to include:

1. Name field
2. Short description field
3. Banner upload field
4. Optional sort order field
5. Active toggle
6. Image preview

### Category List UI

In [assets/js/dashboard-ui.js](assets/js/dashboard-ui.js), extend category rendering to show:

1. Category name
2. Description preview
3. Banner thumbnail
4. Event count
5. Active/inactive badge
6. Created by / updated by metadata if desired
7. Inline reorder actions or sort-order editing

### Admin User Story

When an admin creates a category:

1. They upload the category banner
2. They save the category
3. The category becomes available on the homepage if active
4. The category card uses the uploaded banner instead of hardcoded Unsplash images

## Homepage Changes

### Categories Section in [public/index.php](public/index.php)

Replace the hardcoded `$katMeta` array with DB-driven content.

Each homepage category card should use:

1. `emri`
2. `pershkrimi_shkurter`
3. `banner_path`
4. `event_count`
5. Optional fallback icon or color treatment

### Initial Display Limit

Recommended default limit:

1. Show first 6 categories on desktop
2. Show the same ordered subset on mobile

### Show More Button

Add a button below the grid:

1. Label: `Shiko më shumë`
2. Alternative label: `Shiko më shumë kategori`

Possible behaviors:

1. Expand inline and reveal remaining categories
2. Navigate to a dedicated categories page
3. Navigate to the events page with category browsing enabled

Recommended first implementation:

1. Expand inline on the homepage for a simple UX
2. Hide the button when all categories are visible

### Empty State

If there are no active categories:

1. Show a clean empty state
2. Do not render broken cards or empty image shells

## Event Section Notes

The homepage event carousel already uses event banners from the `Eventi.banner` field. This is good and should remain.

However, the event and category systems should become visually aligned:

1. Event cards use event banners uploaded by admins per event
2. Category cards use category banners uploaded by admins per category
3. Both should use the same fallback asset strategy

## Suggested Implementation Order

### Phase 1: Data Model

1. Add migration for new `Kategoria` fields
2. Update API validation and persistence
3. Add optional homepage-specific list output

### Phase 2: Admin UI

1. Extend category create form
2. Add banner upload and preview
3. Extend edit UI for categories
4. Show category status and sort order

### Phase 3: Homepage

1. Replace hardcoded category metadata with DB content
2. Add initial category limit
3. Add `Shiko më shumë` behavior
4. Add fallback handling for missing banners

### Phase 4: QA

1. Verify category creation with banner upload
2. Verify homepage renders uploaded category banners
3. Verify inactive categories do not appear on homepage
4. Verify `Shiko më shumë` works on desktop and mobile
5. Verify filtering from category cards routes correctly into events

## Acceptance Criteria

1. Admin can create a category with uploaded banner and short description.
2. Newly created active category appears on the homepage without hardcoded edits.
3. Homepage initially shows a limited number of categories.
4. `Shiko më shumë` reveals the rest or routes cleanly to a full listing.
5. Category cards no longer depend on hardcoded image mappings in [public/index.php](public/index.php).
6. The system remains usable when there are zero, few, or many categories.

## Additional Notes After Cleanup

The database cleanup removed all non-user content tables, including categories, events, requests, messages, notifications, and reports. After cleanup:

1. Admins will need to recreate categories and events
2. The homepage category and event sections will remain empty until new content is created
3. This makes the next implementation phase cleaner because category content can be rebuilt with the new banner-driven structure