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
        text: 'Guide',
        items: [
          { text: 'Introduction', link: '/guide/introduction' },
          { text: 'Installation', link: '/guide/installation' },
          { text: 'Quick Start', link: '/guide/quick-start' },
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
        ],
      },
      {
        text: 'UI Components',
        items: [
          { text: 'Form Components', link: '/ui/form-components' },
          { text: 'Table Columns', link: '/ui/table-columns' },
          { text: 'Actions', link: '/ui/actions' },
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
          { text: 'API Reference', link: '/reference/api' },
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
