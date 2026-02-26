# RecipeNest

A full-stack web application for discovering, sharing, and organizing recipes.

## Project Structure

```
RecipeNest/
├── backend/                 # Node.js/Express server
│   ├── config/             # Configuration files (database, environment)
│   ├── controllers/        # Request handlers
│   ├── models/             # Data models
│   ├── routes/             # API routes
│   ├── middleware/         # Custom middleware
│   └── utils/              # Utility functions
├── frontend/               # Client-side code
│   ├── components/         # Reusable React/HTML components
│   ├── pages/              # Page templates
│   ├── js/                 # JavaScript files
│   ├── css/                # Stylesheets
│   └── public/             # Static assets (images, icons)
├── database/               # Database files and schemas
│   ├── schema.sql          # Database schema
│   └── seed.sql            # Sample data
└── docs/                   # Documentation
```

## Getting Started

### Prerequisites
- Node.js v14+
- MySQL/MariaDB

### Installation

1. Clone the repository
```bash
git clone https://github.com/BraxtonFails/RecipeNest.git
cd RecipeNest
```

2. Install dependencies
```bash
npm install
cd backend && npm install
```

3. Set up environment variables
```bash
cp .env.example .env
# Edit .env with your configuration
```

4. Initialize the database
```bash
mysql -u root -p < database/schema.sql
mysql -u root -p < database/seed.sql
```

5. Start the development server
```bash
npm run dev
```

## Features

- User authentication and profiles
- Recipe creation and sharing
- Search and filter recipes
- Save favorite recipes
- Community reviews and ratings

## Tech Stack

- **Backend:** Node.js, Express.js
- **Frontend:** HTML, CSS, JavaScript
- **Database:** MySQL
- **Tools:** Git, VS Code

## Contributing

Feel free to submit issues or pull requests to improve the project.

## License

This project is licensed under the MIT License.
