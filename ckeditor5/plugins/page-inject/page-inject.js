(function() {

let availableTemplates = {};

const Command = window.ckeditor5.classes.core.command.class;
const { findOptimalInsertionPosition } = window.ckeditor5.classes.widget.utils;
const { showPagePicker, showSettingsPopup } = window.ckeditor5.exports;

const itemTypes = {
  page: 'Page Injection',
  content: 'Content Injection',
};

window.ckeditor5.addHook('hookInit', () => {
  window.ckeditor5.addButtonGroup('page-inject', {
    label: 'Page Inject',
  });

  window.ckeditor5.addButton('page-inject-page', {
    group: 'page-inject',
    command: { name: 'page-inject', params: { type: 'page' } },
    label: 'Page Injection',
  });

  window.ckeditor5.addButton('page-inject-content', {
    group: 'page-inject',
    command: { name: 'page-inject', params: { type: 'content' } },
    label: 'Content Injection',
  });
});

window.ckeditor5.addHook('hookMarkdowntoHTML', {
  weight: -50,
  async handler(options, input) {
    let output = input;

    const items = [...output.matchAll(/\[plugin:(?<type>page|content)-inject\]\((?<route>[^?)]+)(?<query>\?[^)]*)?\)/g)];

    const body = new FormData();
    const reqUrl = `${window.GravAdmin.config.base_url_relative}/task:pageInjectData`;

    items.forEach((matches) => {
      body.append('routes[]', matches.groups.route);
    });

    if (!items.length) {
      body.append('routes[]', 'not_exists_route');
    }

    const resp = await fetch(reqUrl, { body, method: 'POST' })
      .then((resp) => (resp.ok ? resp.json() : null))
      .then((resp) => (resp && resp.status !== 'error' ? resp : {}));
    
    availableTemplates = resp.available_templates;

    const pages = resp.data
      .filter((page) => page.status === 'success')
      .reduce((acc, page) => ({ ...acc, [page.data.route]: page.data }), {});

    items.forEach((matches) => {
      const { type, route, query } = matches.groups;
      const template = new URLSearchParams(query).get('template') || '';
      const title = (pages[route] && pages[route].title) || '';
      const modified = (pages[route] && pages[route].modified) || '';

      output = output.replace(matches[0], `[[page-inject type="${type}" title="${title}" route="${route}" template="${template}" modified="${modified}"]]`);
    });

    return output;
  },
});

window.ckeditor5.addHook('hookMarkdowntoHTML', {
  weight: 50,
  handler(options, input) {
    let output = input;

    output = output.replace(/(\[\[page-inject(?<attributes>[^\]]*)\]\])/g, (...matches) => {
      const { attributes } = matches.pop();
      return `<page-inject${attributes}></page-inject>`;
    });

    return output;
  },
});

window.ckeditor5.addHook('hookHTMLtoMarkdown', {
  weight: -50,
  handler(options, editor, input) {
    let output = input;

    output = output.replace(/<page-inject[^>]*>(((?!(<\/page-inject>)).)|\n)*<\/page-inject>/g, (...matches) => {
      const domPageInject = new DOMParser().parseFromString(matches[0], 'text/html').body.firstChild;

      const type = domPageInject.getAttribute('type');
      const route = domPageInject.getAttribute('route');
      const template = domPageInject.getAttribute('template');
      const query = new URLSearchParams();

      if (template) {
        query.set('template', template);
      }

      const queryString = query.toString()
        ? `?${query.toString()}`
        : '';

      return `<p>[plugin:${type}-inject](${route}${queryString})</p>`;
    });

    return output;
  },
});

class GravPageInjectCommand extends Command {
  execute(params) {
    showPagePicker((page) => {
      const textPageInject = `
        <page-inject
          type="${params.type}"
          title="${page.name}"
          route="${page.value}"
          template=""
          modified="${page.modified}"
        ></page-inject>`;

      const viewPageInject = this.editor.data.processor.toView(textPageInject).getChild(0);
      const modelPageInject = this.editor.data.toModel(viewPageInject).getChild(0);
      const { parent } = this.editor.model.document.selection.focus;

      this.editor.model.change((modelWriter) => {
        if (parent && parent.name === 'paragraph' && parent.childCount === 0) {
          modelWriter.remove(parent);
        }

        const insertPosition = findOptimalInsertionPosition(this.editor.model.document.selection, this.editor.model);
        modelWriter.insert(modelPageInject, insertPosition);
      });
    });
  }
}

window.ckeditor5.addPlugin('GravPageInject', {
  init() {
    this.editor.commands.add('page-inject', new GravPageInjectCommand(this.editor));

    this.editor.model.schema.register('page-inject', {
      allowWhere: '$block',
      allowContentOf: '$root',
      allowAttributes: [
        'type',
        'title',
        'route',
        'template',
        'modified',
      ],
    });

    this.editor.conversion.for('upcast').elementToElement({
      view: 'page-inject',
      model: (viewElement, modelWriter) => {
        const attributes = [...viewElement.getAttributes()]
          .reduce((acc, pair) => ({ ...acc, [pair.shift()]: pair.pop() }), {});

        return getPageInject(this.editor, modelWriter, attributes);
      },
    });

    this.editor.conversion.for('downcast').elementToElement({
      model: 'page-inject',
      view: (modelElement, viewWriter) => {
        const attributes = [...modelElement.getAttributes()]
          .reduce((acc, pair) => ({ ...acc, [pair.shift()]: pair.pop() }), {});

        return viewWriter.createContainerElement('page-inject', attributes);
      },
    });
  },
});

function getPageInject(editor, modelWriter, attributes) {
  const pageInject = modelWriter.createElement('page-inject', attributes);

  const container = modelWriter.createElement('div_readonly', { class: 'pi-wrapper' });
  modelWriter.append(container, pageInject);

  const typeText = itemTypes[attributes.type];

  const type = modelWriter.createElement('div', { class: 'pi-type' });
  modelWriter.appendText(itemTypes[attributes.type] || '', type);
  modelWriter.append(type, container);

  const title = modelWriter.createElement('div', { class: 'pi-title' });
  modelWriter.appendText(attributes.title || '', title);
  modelWriter.append(title, container);

  const route = modelWriter.createElement('div', { class: 'pi-route' });
  modelWriter.append(route, container);

  const routeLink = modelWriter.createElement('a_reserved', { target: '_blank', href: attributes.route || '' });
  modelWriter.appendText(attributes.route || '', routeLink);
  modelWriter.append(routeLink, route);

  const routeSettings = getGearButton(modelWriter, { class: 'pi-route-settings' }, {
    click: () => {
      showPagePicker((page) => {
        if (page.value === pageInject.getAttribute('route')) {
          return;
        }

        editor.model.change((modelWriter) => {
          const newAttributes = [...pageInject.getAttributes()]
            .reduce((acc, pair) => ({ ...acc, [pair.shift()]: pair.pop() }), {});

          newAttributes.title = page.name;
          modelWriter.setAttribute('title', page.name, pageInject);

          newAttributes.route = page.value;
          modelWriter.setAttribute('route', page.value, pageInject);

          newAttributes.modified = page.modified;
          modelWriter.setAttribute('modified', page.modified, pageInject);

          const newPageInject = getPageInject(editor, modelWriter, newAttributes);

          [...pageInject.getChildren()].forEach((childItem) => modelWriter.remove(childItem));
          [...newPageInject.getChildren()].forEach((childItem) => modelWriter.append(childItem, pageInject));
        });
      });
    },
  });

  modelWriter.append(routeSettings, container);

  const templateValue = attributes.template
    ? availableTemplates[attributes.template]
      ? `${availableTemplates[attributes.template]} template`
      : `${attributes.template} template`
    : 'No template selected';

  const template = modelWriter.createElement('div', { class: 'pi-template' });
  modelWriter.appendText(templateValue, template);
  modelWriter.append(template, container);

  const modifiedValue = !isNaN(new Date(+attributes.modified))
    ? `Modified at ${new Date(+attributes.modified * 1000).toString()}`
    : 'No modified date';

  const modified = modelWriter.createElement('div', { class: 'pi-modified' });
  modelWriter.appendText(modifiedValue, modified);
  modelWriter.append(modified, container);

  const settings = getGearButton(modelWriter, { class: 'pi-settings' }, {
    click: (data, event) => {
      const newAttributes = [...pageInject.getAttributes()]
        .reduce((acc, pair) => ({ ...acc, [pair.shift()]: pair.pop() }), {});

      showSettingsPopup({
        editor,
        modelItem: pageInject,
        domDisplayPoint: event.target,
        attributes: {
          type: {
            title: 'Type',
            widget: {
              type: 'select',
              values: Object.keys(itemTypes).map((value) => ({ value, label: itemTypes[value] })),
            },
          },
          template: {
            title: 'Template',
            widget: {
              type: 'select',
              values: Object.keys(availableTemplates).map((value) => ({ value, label: availableTemplates[value] })),
            },
          },
        },
        currentAttributes: {
          type: pageInject.getAttribute('type'),
          template: pageInject.getAttribute('template'),
        },
        changeAttribute(attrName, attrValue) {
          editor.model.change((modelWriter) => {
            const newAttributes = [...pageInject.getAttributes()]
              .reduce((acc, pair) => ({ ...acc, [pair.shift()]: pair.pop() }), {});

            newAttributes[attrName] = attrValue;
            modelWriter.setAttribute(attrName, attrValue, pageInject);

            const newPageInject = getPageInject(editor, modelWriter, newAttributes);

            [...pageInject.getChildren()].forEach((childItem) => modelWriter.remove(childItem));
            [...newPageInject.getChildren()].forEach((childItem) => modelWriter.append(childItem, pageInject));
          });
        },
      });
    },
  });

  modelWriter.append(settings, container);

  return pageInject;
}

function getGearButton(modelWriter, attributes, events) {
  const modelSvg = modelWriter.createElement('svg', {
    ...attributes,
    viewBox: '0 0 24 24',
    fill: 'currentColor',
    stroke: 'none',
    events,
  });

  const modelSvgPath = modelWriter.createElement('path', {
    d: 'M9 4.58V4c0-1.1.9-2 2-2h2a2 2 0 0 1 2 2v.58a8 8 0 0 1 1.92 1.11l.5-.29a2 2 0 0 1 2.74.73l1 1.74a2 2 0 0 1-.73 2.73l-.5.29a8.06 8.06 0 0 1 0 2.22l.5.3a2 2 0 0 1 .73 2.72l-1 1.74a2 2 0 0 1-2.73.73l-.5-.3A8 8 0 0 1 15 19.43V20a2 2 0 0 1-2 2h-2a2 2 0 0 1-2-2v-.58a8 8 0 0 1-1.92-1.11l-.5.29a2 2 0 0 1-2.74-.73l-1-1.74a2 2 0 0 1 .73-2.73l.5-.29a8.06 8.06 0 0 1 0-2.22l-.5-.3a2 2 0 0 1-.73-2.72l1-1.74a2 2 0 0 1 2.73-.73l.5.3A8 8 0 0 1 9 4.57zM7.88 7.64l-.54.51-1.77-1.02-1 1.74 1.76 1.01-.17.73a6.02 6.02 0 0 0 0 2.78l.17.73-1.76 1.01 1 1.74 1.77-1.02.54.51a6 6 0 0 0 2.4 1.4l.72.2V20h2v-2.04l.71-.2a6 6 0 0 0 2.41-1.4l.54-.51 1.77 1.02 1-1.74-1.76-1.01.17-.73a6.02 6.02 0 0 0 0-2.78l-.17-.73 1.76-1.01-1-1.74-1.77 1.02-.54-.51a6 6 0 0 0-2.4-1.4l-.72-.2V4h-2v2.04l-.71.2a6 6 0 0 0-2.41 1.4zM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm0-2a2 2 0 1 0 0-4 2 2 0 0 0 0 4z',
  });

  modelWriter.append(modelSvgPath, modelSvg);

  return modelSvg;
}

})();
