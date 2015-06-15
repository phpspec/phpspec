default:
  suites:
    application:
      contexts: [ ApplicationContext, FilesystemContext ]
      filters: { tags: ~@isolated }
    isolated:
      contexts: [ IsolatedProcessContext, FilesystemContext ]
      filters: { tags: @isolated }
    smoke:
      contexts: [ IsolatedProcessContext, FilesystemContext ]
      filters: { tags: @smoke && ~@isolated }
  formatters:
    progress: ~

no-smoke:
  suites:
    smoke: ~
