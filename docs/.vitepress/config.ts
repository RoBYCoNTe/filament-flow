import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'Filament Flow',
  description: 'Business Process Manager for Filament',
  base: '/filament-flow/',

  themeConfig: {
    logo: '/logo.svg',

    nav: [
      { text: 'Guide', link: '/guide/introduction' },
      { text: 'Panel', link: '/panel/integration' },
      { text: 'Workflows', link: '/workflows/database-driven' },
      { text: 'UI', link: '/ui/form-components' },
      { text: 'Examples', link: '/examples/order-workflow' },
      { text: 'Reference', link: '/reference/configuration' },
      {
        text: 'GitHub',
        link: 'https://github.com/RoBYCoNTe/filament-flow',
      },
    ],

    sidebar: [
      {
        text: 'Getting Started',
        items: [
          { text: 'Introduction', link: '/guide/introduction' },
          { text: 'Installation', link: '/guide/installation' },
          { text: 'Quick Start', link: '/guide/quick-start' },
          { text: 'Core Concepts', link: '/guide/concepts' },
        ],
      },
      {
        text: 'Guides',
        items: [
          { text: 'Admin Panel Setup', link: '/guide/admin-panel' },
          { text: 'Testing', link: '/guide/testing' },
          { text: 'Commands', link: '/guide/commands' },
          { text: 'Performance', link: '/guide/performance' },
          { text: 'Security', link: '/guide/security' },
          { text: 'Troubleshooting', link: '/guide/troubleshooting' },
        ],
      },
      {
        text: 'Panel Integration',
        items: [
          { text: 'Plugin Registration', link: '/panel/integration' },
          { text: 'Multi-Tenancy', link: '/panel/multi-tenancy' },
          { text: 'Relation Managers', link: '/panel/relation-managers' },
        ],
      },
      {
        text: 'Workflows',
        items: [
          { text: 'Database-Driven', link: '/workflows/database-driven' },
          { text: 'Access Control', link: '/workflows/access-control' },
          { text: 'Notifications', link: '/workflows/notifications' },
          { text: 'Lifecycle Events', link: '/workflows/events' },
          { text: 'Conditions & Actions', link: '/workflows/conditions' },
          { text: 'Scheduled Checks', link: '/workflows/scheduled-checks' },
          { text: 'Side Effects', link: '/workflows/side-effects' },
        ],
      },
      {
        text: 'UI Components',
        items: [
          { text: 'Form Components', link: '/ui/form-components' },
          { text: 'Table Columns', link: '/ui/table-columns' },
          { text: 'Actions', link: '/ui/actions' },
          { text: 'Infolist Components', link: '/ui/infolist-components' },
          { text: 'Assignment Management', link: '/ui/assignments' },
        ],
      },
      {
        text: 'Examples',
        items: [
          { text: 'Order Workflow', link: '/examples/order-workflow' },
        ],
      },
      {
        text: 'Reference',
        items: [
          { text: 'Configuration', link: '/reference/configuration' },
          { text: 'Contracts & Interfaces', link: '/reference/contracts' },
          { text: 'Programmatic Services', link: '/reference/services' },
          { text: 'Database Schema', link: '/reference/database-schema' },
          { text: 'API Reference', link: '/reference/api' },
          { text: 'Upgrade Guide', link: '/reference/upgrade' },
        ],
      },
    ],

    search: {
      provider: 'local',
    },

    socialLinks: [
      { icon: 'github', link: 'https://github.com/RoBYCoNTe/filament-flow' },
    ],

    footer: {
      message: 'Proprietary software. All rights reserved.',
      copyright: 'Copyright © Roberto Conte Rosito',
    },
  },
})
