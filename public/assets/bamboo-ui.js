const COMPONENT_RENDERERS = {
  page: renderPage,
  hero: renderHero,
  'feature-grid': renderFeatureGrid,
  'stat-grid': renderStatGrid,
  faq: renderFaq,
  'code-snippet': renderSnippet,
  footer: renderFooter,
};

const GSAP_CDN_URL = 'https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js';

let cachedGsap = null;
let gsapLoaderPromise = null;

export function renderTemplate(root, template) {
  if (!root || !template) {
    return;
  }

  const normalized = normalizeTemplate(template);
  if (!normalized) {
    return;
  }

  root.classList.add('bamboo-page');

  const fragment = document.createDocumentFragment();
  for (const child of normalized.children) {
    const node = renderComponent(child);
    if (node) {
      fragment.appendChild(node);
    }
  }

  root.replaceChildren(fragment);
}

function normalizeTemplate(template) {
  if (typeof template !== 'object' || template === null) {
    return null;
  }

  if (template.component !== 'page') {
    return null;
  }

  const children = Array.isArray(template.children) ? template.children : [];
  return { component: 'page', children };
}

function renderComponent(node) {
  if (!node || typeof node !== 'object') {
    return null;
  }

  const renderer = COMPONENT_RENDERERS[node.component];
  if (!renderer) {
    return null;
  }

  return renderer(node);
}

function renderPage(node) {
  const fragment = document.createDocumentFragment();
  const children = Array.isArray(node.children) ? node.children : [];
  for (const child of children) {
    const element = renderComponent(child);
    if (element) {
      fragment.appendChild(element);
    }
  }
  return fragment;
}

function renderHero(node) {
  const section = createElement('section', 'bamboo-hero');

  if (Array.isArray(node.badges) && node.badges.length > 0) {
    const badges = createElement('div', 'bamboo-hero-badges');
    for (const badge of node.badges) {
      badges.appendChild(renderBadge(badge));
    }
    section.appendChild(badges);
  }

  if (typeof node.title === 'string' && node.title !== '') {
    const title = document.createElement('h1');
    title.textContent = node.title;
    section.appendChild(title);
  }

  if (typeof node.description === 'string' && node.description !== '') {
    const description = document.createElement('p');
    description.textContent = node.description;
    section.appendChild(description);
  }

  if (Array.isArray(node.actions) && node.actions.length > 0) {
    const actions = createElement('div', 'bamboo-hero-actions');
    for (const action of node.actions) {
      const link = createActionLink(action);
      if (link) {
        actions.appendChild(link);
      }
    }
    section.appendChild(actions);
  }

  return section;
}

function renderBadge(badge) {
  const pill = createElement('span', 'bamboo-pill');

  if (typeof badge.highlight === 'string' && badge.highlight !== '') {
    const strong = document.createElement('strong');
    strong.textContent = badge.highlight;
    pill.appendChild(strong);
  }

  if (typeof badge.label === 'string' && badge.label !== '') {
    const label = createElement('span', 'label');
    label.textContent = badge.label;
    pill.appendChild(label);
  }

  return pill;
}

function createActionLink(action) {
  if (typeof action !== 'object' || action === null) {
    return null;
  }

  const href = typeof action.href === 'string' && action.href !== '' ? action.href : '#';
  const label = typeof action.label === 'string' ? action.label : '';
  if (label === '') {
    return null;
  }

  const variant = typeof action.variant === 'string' ? action.variant : 'secondary';
  const link = createElement('a', `bamboo-cta ${variant}`.trim());
  link.href = href;
  link.textContent = label;

  if (action.external) {
    link.target = '_blank';
    link.rel = 'noreferrer';
  }

  return link;
}

function renderFeatureGrid(node) {
  const ariaLabel = typeof node.ariaLabel === 'string' && node.ariaLabel !== ''
    ? node.ariaLabel
    : null;
  const section = createElement('section', 'bamboo-grid');
  if (ariaLabel) {
    section.setAttribute('aria-label', ariaLabel);
  }

  const items = Array.isArray(node.items) ? node.items : [];
  for (const item of items) {
    const article = createElement('article', 'bamboo-card');

    if (typeof item.icon === 'string' && item.icon !== '') {
      const icon = createElement('span', 'icon');
      icon.textContent = item.icon;
      article.appendChild(icon);
    }

    if (typeof item.title === 'string' && item.title !== '') {
      const heading = document.createElement('h3');
      heading.textContent = item.title;
      article.appendChild(heading);
    }

    if (typeof item.body === 'string' && item.body !== '') {
      const paragraph = document.createElement('p');
      paragraph.textContent = item.body;
      article.appendChild(paragraph);
    }

    section.appendChild(article);
  }

  return section;
}

function renderStatGrid(node) {
  const ariaLabel = typeof node.ariaLabel === 'string' && node.ariaLabel !== ''
    ? node.ariaLabel
    : null;
  const section = createElement('section', 'bamboo-stats');
  if (ariaLabel) {
    section.setAttribute('aria-label', ariaLabel);
  }

  const items = Array.isArray(node.items) ? node.items : [];
  for (const item of items) {
    const wrapper = createElement('dl', 'bamboo-stat');

    if (typeof item.label === 'string' && item.label !== '') {
      const term = document.createElement('dt');
      term.textContent = item.label;
      wrapper.appendChild(term);
    }

    if (typeof item.value === 'string' && item.value !== '') {
      const value = document.createElement('dd');
      value.textContent = item.value;
      wrapper.appendChild(value);
    }

    section.appendChild(wrapper);
  }

  return section;
}

function renderFaq(node) {
  const section = createElement('section', 'bamboo-faq');

  if (typeof node.heading === 'string' && node.heading !== '') {
    const heading = document.createElement('h2');
    heading.textContent = node.heading;
    section.appendChild(heading);
  }

  const items = Array.isArray(node.items) ? node.items : [];
  const itemsWithContent = items.filter((item) => typeof item === 'object' && item !== null);

  for (const item of itemsWithContent) {
    const article = createElement('article', 'bamboo-faq-item');

    const questionText = typeof item.question === 'string' ? item.question : '';
    const answerText = typeof item.answer === 'string' ? item.answer : '';
    if (questionText === '' && answerText === '') {
      continue;
    }

    if (questionText === '') {
      continue;
    }

    if (answerText === '') {
      continue;
    }

    const questionId = `bamboo-faq-${nextFaqId()}`;

    const questionButton = createElement('button', 'bamboo-faq-question');
    questionButton.type = 'button';
    questionButton.id = questionId;
    questionButton.setAttribute('aria-expanded', 'false');
    questionButton.textContent = questionText;
    article.appendChild(questionButton);

    const answer = createElement('div', 'bamboo-faq-answer');
    answer.id = `${questionId}-panel`;
    answer.setAttribute('role', 'region');
    answer.setAttribute('aria-hidden', 'true');
    questionButton.setAttribute('aria-controls', answer.id);

    const body = document.createElement('p');
    body.textContent = answerText;
    answer.appendChild(body);
    article.appendChild(answer);

    section.appendChild(article);
  }

  if (section.children.length > 0) {
    initializeFaqAccordion(section);
  }

  return section;
}

function renderSnippet(node) {
  const ariaLabel = typeof node.ariaLabel === 'string' && node.ariaLabel !== ''
    ? node.ariaLabel
    : null;
  const section = createElement('section', 'bamboo-snippet');
  if (ariaLabel) {
    section.setAttribute('aria-label', ariaLabel);
  }

  const pre = document.createElement('pre');
  const lines = Array.isArray(node.lines) ? node.lines : [];
  pre.textContent = lines.join('\n');
  section.appendChild(pre);

  return section;
}

function renderFooter(node) {
  const footer = createElement('footer', 'bamboo-footer');
  const content = Array.isArray(node.content) ? node.content : [];

  for (const piece of content) {
    if (!piece || typeof piece !== 'object') {
      continue;
    }

    if (piece.type === 'text' && typeof piece.value === 'string') {
      footer.appendChild(document.createTextNode(piece.value));
      continue;
    }

    if (piece.type === 'link' && typeof piece.label === 'string' && piece.label !== '') {
      const link = document.createElement('a');
      link.textContent = piece.label;
      link.href = typeof piece.href === 'string' && piece.href !== '' ? piece.href : '#';
      if (piece.external) {
        link.target = '_blank';
        link.rel = 'noreferrer';
      }
      footer.appendChild(link);
      continue;
    }
  }

  return footer;
}

function createElement(tag, className) {
  const element = document.createElement(tag);
  if (className) {
    element.className = className;
  }
  return element;
}

function nextFaqId() {
  if (typeof nextFaqId.counter !== 'number') {
    nextFaqId.counter = 0;
  }
  nextFaqId.counter += 1;
  return nextFaqId.counter;
}

function initializeFaqAccordion(section) {
  const items = section.querySelectorAll('.bamboo-faq-item');
  if (items.length === 0) {
    return;
  }

  ensureGsap();

  for (const item of items) {
    const trigger = item.querySelector('.bamboo-faq-question');
    const answer = item.querySelector('.bamboo-faq-answer');
    if (!trigger || !answer) {
      continue;
    }

    trigger.addEventListener('click', () => {
      const expanded = trigger.getAttribute('aria-expanded') === 'true';
      toggleFaqItem(item, !expanded);
    });

    toggleFaqItem(item, false, { skipAnimation: true });
  }
}

function toggleFaqItem(item, shouldExpand, options = {}) {
  const trigger = item.querySelector('.bamboo-faq-question');
  const answer = item.querySelector('.bamboo-faq-answer');
  if (!trigger || !answer) {
    return;
  }

  trigger.setAttribute('aria-expanded', shouldExpand ? 'true' : 'false');
  answer.setAttribute('aria-hidden', shouldExpand ? 'false' : 'true');
  item.classList.toggle('is-open', shouldExpand);

  if (options.skipAnimation) {
    answer.style.height = shouldExpand ? 'auto' : '0px';
    return;
  }

  ensureGsap().then((gsapInstance) => {
    if (!gsapInstance) {
      answer.style.height = shouldExpand ? 'auto' : '0px';
      return;
    }

    gsapInstance.killTweensOf(answer);

    if (shouldExpand) {
      gsapInstance.set(answer, { height: 'auto' });
      const targetHeight = answer.getBoundingClientRect().height;
      gsapInstance.fromTo(
        answer,
        { height: 0 },
        {
          height: targetHeight,
          duration: 0.35,
          ease: 'power2.out',
          onComplete: () => {
            answer.style.height = 'auto';
          },
        },
      );
    } else {
      const currentHeight = answer.getBoundingClientRect().height;
      gsapInstance.fromTo(
        answer,
        { height: currentHeight },
        {
          height: 0,
          duration: 0.3,
          ease: 'power2.inOut',
          onComplete: () => {
            answer.style.height = '0px';
          },
        },
      );
    }
  });
}

function ensureGsap() {
  if (cachedGsap) {
    return Promise.resolve(cachedGsap);
  }

  if (typeof window !== 'undefined' && window.gsap) {
    cachedGsap = window.gsap;
    return Promise.resolve(cachedGsap);
  }

  if (!gsapLoaderPromise) {
    gsapLoaderPromise = new Promise((resolve) => {
      if (typeof document === 'undefined') {
        resolve(null);
        return;
      }

      const existing = document.querySelector(`script[src="${GSAP_CDN_URL}"]`);
      if (existing) {
        if (existing.getAttribute('data-gsap-loaded') === 'true' || (typeof window !== 'undefined' && window.gsap)) {
          cachedGsap = window.gsap || null;
          resolve(cachedGsap);
          return;
        }
        existing.addEventListener('load', () => {
          cachedGsap = window.gsap || null;
          resolve(cachedGsap);
        });
        existing.addEventListener('error', () => {
          gsapLoaderPromise = null;
          resolve(null);
        });
        return;
      }

      const script = document.createElement('script');
      script.src = GSAP_CDN_URL;
      script.async = true;
      script.onload = () => {
        cachedGsap = window.gsap || null;
        script.setAttribute('data-gsap-loaded', 'true');
        resolve(cachedGsap);
      };
      script.onerror = () => {
        gsapLoaderPromise = null;
        resolve(null);
      };
      script.dataset.gsapprovider = 'bamboo-ui';
      document.head.appendChild(script);
    });
  }

  return gsapLoaderPromise;
}
