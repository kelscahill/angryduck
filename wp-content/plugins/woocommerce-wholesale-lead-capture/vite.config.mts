import { defineConfig, loadEnv } from 'vite'
import { fileURLToPath } from 'url'
import { lstatSync, readdirSync, statSync, existsSync } from 'fs'
import legacy from '@vitejs/plugin-legacy'
import vue from '@vitejs/plugin-vue'
import liveReload from 'vite-plugin-live-reload'
import externalGlobals from 'rollup-plugin-external-globals'
import * as path from 'path'

// https://vitejs.dev/config/
// @ts-ignore
export default defineConfig(({ command, mode, ssrBuild }) => {
  const isDirectory = source => lstatSync(source).isDirectory()
  let entries = {}

  /***************************************************************************
   * Vue App scripts entry points
   ***************************************************************************
   *
   * If we are building app scripts, we list all the app entry file in the
   * 'apps' directory and add them to the entries object.
   *
   */
  const vueAppEntries = source => readdirSync(source).forEach(view => {
    if (isDirectory(path.resolve(source, view))) {
      readdirSync(path.resolve(source, view)).forEach(app => {
        const filePath = fileURLToPath(new URL(
          `./apps/${view}/index.ts`,
          import.meta.url
        ))
        if (existsSync(filePath)) {
          entries[`apps/${view}/index`] = filePath
        }
      })
    }
  })
  vueAppEntries(path.resolve(__dirname, './apps'))

  const getAllFiles = (source, apps = []) => {
    const files = readdirSync(source)
    apps = apps || []

    files.forEach((file) => {
      if (statSync(source + '/' + file).isDirectory()) {
        apps = getAllFiles(source + '/' + file, apps)
      } else {
        apps.push(path.join(__dirname, source, '/', file))
      }
    })

    return apps
  }
  /***************************************************************************
   * Include all vanilla app scripts
   ***************************************************************************
   *
   * We include all vanilla app scripts from the 'vanilla' directory.
   */
  const vanillaAppEntries = (source) => {
    const vanillaApps = getAllFiles(source)
    vanillaApps.forEach((app) => {
      const fileName = app.split(`${path.sep}apps${path.sep}`)[1],
        filePath = fileURLToPath(
          new URL(`./apps/${fileName}`, import.meta.url)
        )
      if (existsSync(filePath)) {
        entries[`${path.dirname(fileName)}/${path.basename(fileName, '.ts')}`] = filePath
      }
    })
  }
  if (existsSync(path.resolve(__dirname, './apps/vanilla'))) {
    vanillaAppEntries(path.resolve(__dirname, './apps/vanilla'))
  }

  const config = {
    resolve: {
      alias: [
        /***************************************************************************
         * Alias for Vue
         ***************************************************************************
         *
         * Alias for Vue to use the Vue 3 ESM build. This is needed for the
         * WordPress JS hooks to work with `component`'s `is` directive with
         * { template: ... } value.
         *
         */
        { find: /^vue$/, replacement: 'vue/dist/vue.esm-bundler.js' },
        /***************************************************************************
         * Enable @ alias for root directory
         ***************************************************************************
         *
         * We add support for '@' alias to the `<root>/apps` directory.
         */
        { find: /^@(?=\/)/, replacement: path.resolve(__dirname, './apps') },
        /***************************************************************************
         * Enable ~ alias for the node_modules directory
         ***************************************************************************
         *
         * We add support for '~' alias to the `<root>/node_modules` directory.
         *
         */
        { find: /^~/, replacement: '' }
      ]
    },
    plugins: [
      /***************************************************************************
       * Load Vue plugin
       ***************************************************************************
       *
       * As described in the Vite documentation for @vitejs/plugin-vue, this
       * is the all-in-one Vite plugin for Vue projects.
       */
      vue(),
    ],
  }

  if (command === 'serve') {
    /***************************************************************************
     * Hot module replacement (HMR) mode
     ***************************************************************************
     *
     * We configure the Vite server in HMR mode to use HTTPS protocol if the
     * environment variables 'VITE_DEV_SERVER_HTTPS_KEY', 'VITE_DEV_SERVER_HTTPS_CERT',
     * and 'VITE_DEV_SERVER_HTTPS_CA' are set to the path where the files can be
     * located to enable 'HTTPS', otherwise, protocol defaults to HTTP.
     *
     */
    const env = loadEnv(mode, process.cwd())
    const https = env.VITE_DEV_SERVER_HTTPS_KEY && env.VITE_DEV_SERVER_HTTPS_CERT
      ? {
        key: env.VITE_DEV_SERVER_HTTPS_KEY,
        cert: env.VITE_DEV_SERVER_HTTPS_CERT,
      } : false

    return {
      ...config,
      plugins: [
        ...config.plugins,
        /***************************************************************************
         * Live reload plugin
         ***************************************************************************
         *
         * We add the live reload plugin here in order for the page to automatically
         * reload when there are any changes to non-JS files (e.g. PHP files).
         *
         */
        liveReload('**/*.php'),
      ],
      server: {
        https,
        cors: true,
        headers: {
          'Access-Control-Allow-Origin': '*'
        },
        origin: env.VITE_DEV_SERVER_ORIGIN || (https && 'https://localhost:3000') || 'http://localhost:3000',
        host: env.VITE_DEV_SERVER_HOST || 'localhost',
        port: env.VITE_DEV_SERVER_PORT ? parseInt(env.VITE_DEV_SERVER_PORT) : 3000,
        strictPort: true,
        /***************************************************************************
         * Do not open the browser on server start
         ***************************************************************************
         *
         * We do not open the browser on server start since it's no use for us.
         *
         */
        open: false,
      },
    }
  } else {
    const wpLib = [
      'blocks',
      'components',
      'date',
      'editor',
      'block-editor',
      'element',
      'i18n',
      'data',
      'html-entities',
      'keycodes',
      'url',
      'plugins',
      'edit-post',
    ],
      wpExternals = wpLib.reduce((externals, lib) => {
        /***************************************************************************
         * External WordPress libraries
         ***************************************************************************
         *
         * The `externalName` below would be the name of the global variable that
         * WordPress exposes for the library. For example, the `@wordpress/element`
         * library is exposed as `wp.element` global variable.
         *
         * The regex transforms the hyphenated library name to camelCase, so that
         * the `block-editor` library for example, is transformed to `blockEditor`.
         *
         */
        const externalName = lib.replace(/-([a-z])/g, g => g[1].toUpperCase())
        externals[`@wordpress/${lib}`] = `wp.${externalName}`
        return externals
      }, {})

    return {
      ...config,
      plugins: [
        ...config.plugins,
        /***************************************************************************
         * Target legacy browser
         ***************************************************************************
         *
         * We add the target legacy browsers here to transpile the code to.
         *
         */
        legacy({
          targets: ['defaults', 'last 2 versions', '> 0.2%', 'not dead'],
        })
      ],
      build: {
        outDir: path.resolve(__dirname, './dist'),
        /***************************************************************************
         * Production build manifest file
         ***************************************************************************
         *
         * We add the manifest file here to generate a manifest file that contains
         * a mapping of all asset filenames to their corresponding output file.
         *
         */
        manifest: true,
        /***************************************************************************
         * Production build sourcemap
         ***************************************************************************
         *
         * Sourcemap is disabled by default in production build. Just making sure
         * it's disabled here.
         *
         */
        sourcemap: false,
        /***************************************************************************
         * Rollup build options
         ***************************************************************************
         *
         * Rollup config options.
         *
         */
        rollupOptions: {
          /***************************************************************************
           * Entry files
           ***************************************************************************
           *
           * We include the entries object here to define the entry files for the
           * build.
           *
           */
          input: entries,
          /***************************************************************************
           * Output files configuration
           ***************************************************************************
           *
           * Output files configuration.
           *
           */
          output: {
            /***************************************************************************
             * Entry files production build name
             ***************************************************************************
             *
             * The entry file name should be something like:
             * 'blocks/[block-name]/index.js' for the editor and,
             * 'blocks/[block-name]/script/index.js' for the frontend.
             *
             * For the app entry files, the file name should be something like:
             * 'apps/[view]/[app-name]/index.js'. Where:
             *
             * - [view] is the view name (e.g. 'admin' or 'front')
             *
             */
            entryFileNames: '[name].[hash].js',
            /***************************************************************************
             * Chunk files production build name
             ***************************************************************************
             *
             * We put the chunk files in a subdirectory within the main entry file's
             * directory.
             *
             */
            chunkFileNames: ({ isDynamicEntry, name, facadeModuleId }) => {

              if (isDynamicEntry && facadeModuleId) {
                const fileNameParts = facadeModuleId.split(`apps${path.sep}`)
                if (fileNameParts.length > 1) {
                  const chunkPath = fileNameParts[1].split(path.sep).slice(0, -1).join('/')
                  return `${chunkPath}/[name].[hash].js`
                }
              }

              return 'common/[name].[hash].js'
            },
            /***************************************************************************
             * Asset files production build name
             ***************************************************************************
             *
             * We put the asset files (images, txt, etc.) in a subdirectory within the
             * main entry file's directory.
             *
             */
            assetFileNames: ({ name }) => {
              const fileNameParts = name.split(`apps${path.sep}`)
              let assetPath = name.indexOf('.css') > -1 ? 'css/' : ''
              if (fileNameParts.length > 1) {
                const fileNamePath = fileNameParts[1].split(path.sep)
                assetPath = fileNamePath.slice(0, -1).join('/') + '/'
              }

              return `${assetPath}[name].[hash][extname]`
            },
            sourcemap: false,
          },
          /***************************************************************************
           * Define external libraries
           ***************************************************************************
           *
           * List of external libraries that should not be included in the build as
           * they would be provided by WordPress via the `window` or `wp` global
           * variable. Any libraries that are listed here should also be added to
           * the `externalGlobals` mapping plugin in the `rollupOptions.plugins`.
           *
           */
          external: [
            'lodash',
            'moment',
            'jquery',
            ...Object.keys(wpExternals),
          ],
          plugins: [
            /***************************************************************************
             * External globals mapping
             ***************************************************************************
             *
             * List of external libraries that should be mapped to the `window` or
             * `wp` global variable. Any libraries that are listed here must also be
             * added to the `external` array in the `rollupOptions.external` array in
             * {import_name: global_name} format.
             * E.g.
             *  react: 'window.React'
             *  //...and/or...
             *  '@wordpress/i18n': 'wp.i18n'
             *
             */
            externalGlobals({
              react: 'window.React',
              'react-dom': 'window.ReactDOM',
              lodash: 'window.lodash',
              moment: 'window.moment',
              jquery: 'window.jQuery',
              ...wpExternals,
            }),
          ]
        }
      },
      // Vite 3.2.0-beta.0
      experimental: {
        /***************************************************************************
         * Runtime website URL
         ***************************************************************************
         *
         * We add the runtime website URL here so, the generated dynamic files can
         * be found and correctly loaded.
         *
         */
        renderBuiltUrl: (fileName: string, { hostType }) => {

          if (hostType === 'js') {
            return {
              runtime: `window.wpayObj.pluginDirUrl + '/dist/${fileName}'`
            }
          } else if (hostType === 'css') {
            return {
              runtime: `window.wpayObj.pluginDirUrl + '/dist/css/${fileName}'`
            }
          }

          return fileName
        }
      }
    }
  }
})
