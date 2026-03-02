# RecipeNest

A recipe-sharing web application: discover, create, and share recipes. (Static front-end demo for Client/Server Web Programming.)

---

## Run the project

1. Clone the repo: `git clone https://github.com/BraxtonFails/RecipeNest.git`
2. Open `index.html` in a browser, or serve the folder locally (e.g. `npx serve .` or VS Code Live Server).
3. No build step required; all assets are static HTML, CSS, and JS.

---

## Technologies

- **Front-end:** HTML5, CSS3, JavaScript
- **UI framework:** Bootstrap 5.3
- **Icons:** Bootstrap Icons, Boxicons (login/register)
- **Fonts:** Google Fonts (Playfair Display)
- **Version control:** Git / GitHub
- **Database design:** ERD only (see Supporting Documentation)

---

## Supporting documentation

| Document | Location |
|----------|----------|
| **Project Proposal** | [docs/project-proposal.md](docs/project-proposal.md) |
| **Project Description** | [docs/project-description.md](docs/project-description.md) |
| **Project Requirements** | [docs/project-requirements.md](docs/project-requirements.md) |
| **Database ERD** | [docs/database-erd.md](docs/database-erd.md) |

---

## Profile management (who has access to what)

- **Anonymous visitors:** View landing (featured recipes), About, Register page; can open Login (non-functional).
- **Regular users (demo):** Login link “Continue to Regular User View” → Landing (home). Can use Profile, About, Upload, Register, view recipe detail. No admin access.
- **Admins (demo):** Login link “Login as Admin” → Admin dashboard. Can view/manage recipes, users, and reports; no access granted to other users’ data in this demo (static pages only).

---

## Site structure (tabs/pages)

- **Landing (Home)** – `index.html` – Featured recipe rows, Upload CTA.
- **Profile** – `pages/profile.html` – User profile (demo, non-functional).
- **About** – `pages/about.html` – About RecipeNest and features.
- **Register** – `pages/register.html` – Registration form (demo).
- **Login** – `pages/login.html` – Non-functional login with two demo links: **Regular User View** (Landing) and **Admin View** (admin dashboard).
- **Upload** – `pages/upload.html` – Upload recipe form (demo).
- **Recipe detail** – `pages/recipe.html` – Single recipe view (e.g. Blueberry Pancakes).
- **Admin** – `pages/admin.html` – Admin dashboard (recipes, users, reports).

---

## GitHub

Code is documented and maintained in this repository. Keep the repo updated for the course check.
