function isTypingTarget(target) {
  if (!(target instanceof Element)) return false;
  const tagName = target.tagName;
  return tagName === 'INPUT'
    || tagName === 'TEXTAREA'
    || tagName === 'SELECT'
    || target.isContentEditable;
}
function navigateToShortcut(shortcut) {
  const selector = `a[data-shortcut="${shortcut}"]`;
  const link = document.querySelector(selector);
  if (!(link instanceof HTMLAnchorElement)) return false;
  window.location.href = link.href;
  return true;
}
function initFlashAutoHide() {
  const flashes = document.querySelectorAll('.flash');
  if (flashes.length === 0) return;
  flashes.forEach(message => {
    const isError = message.querySelector('p.error') !== null;
    setTimeout(() => {
      message.style.transition = 'opacity .5s';
      message.style.opacity = '0';
      message.addEventListener('transitionend', () => {
        message.remove();
      }, { once: true });
    }, isError ? 4000 : 2000);
  });
}
function initUsernameInputFilter() {
  const usernameInputs = document.querySelectorAll('input[type="text"][name="username"]');
  if (usernameInputs.length === 0) return;
  usernameInputs.forEach((input) => {
    if (!(input instanceof HTMLInputElement)) return;
    input.addEventListener('input', () => {
      const normalized = input.value.toLowerCase().replace(/[^a-z0-9]/g, '');
      if (normalized === input.value) return;
      input.value = normalized;
    });
  });
}
function confirmDeleteIfEmpty(button) {
  const textarea = document.getElementById('content');
  if (!textarea || textarea.value.trim() !== '') return true;
  const message = button.dataset.confirmMessage || '';
  if (message && !confirm(message)) return false;
  const canDeleteHistory = button.dataset.canDeleteHistory === '1';
  const historyMessage = button.dataset.confirmHistoryMessage;
  const historyField = document.getElementById('delete-history-field');
  if (historyField) {
    historyField.value = '0';
  }
  if (canDeleteHistory && historyMessage && historyField) {
    historyField.value = confirm(historyMessage) ? '1' : '0';
  }
  return true;
}
function initSubmitConfirmHandler() {
  document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    const eventSubmitter = event.submitter;
    const activeElement = document.activeElement;
    const submitter = eventSubmitter instanceof HTMLButtonElement && eventSubmitter.form === form
      ? eventSubmitter
      : (activeElement instanceof HTMLButtonElement && activeElement.form === form ? activeElement : null);
    let message = form.dataset.confirmMessage || '';
    if (submitter instanceof HTMLButtonElement) {
      if (submitter.dataset.skipConfirm === '1') return;
      const buttonMessage = submitter.dataset.confirmMessage || '';
      if (buttonMessage !== '') {
        message = buttonMessage;
      }
    }
    if (message && !confirm(message)) {
      event.preventDefault();
    }
  });
}
function closeDropdowns(e) {
  document.querySelectorAll('.dropdown[open]').forEach(d => {
    if (!e || !d.contains(e.target)) {
      d.removeAttribute('open');
    }
  });
}
function initDropdownAutoClose() {
  document.addEventListener('click', closeDropdowns);
  let closeDropdownsOnScrollScheduled = false;
  document.addEventListener('scroll', () => {
    if (closeDropdownsOnScrollScheduled) return;
    closeDropdownsOnScrollScheduled = true;
    window.requestAnimationFrame(() => {
      closeDropdownsOnScrollScheduled = false;
      if (document.querySelector('.dropdown[open]')) {
        closeDropdowns();
      }
    });
  }, { passive: true });
}
let contextMenu = null;
function hideContextMenu() {
  if (!(contextMenu instanceof HTMLElement)) return;
  contextMenu.remove();
  contextMenu = null;
}
function shouldUseBrowserContextMenu(target) {
  if (target.closest('a[href]')) return true;
  return target.tagName === 'BUTTON'
    || isTypingTarget(target)
    || (window.getSelection()?.toString() ?? '') !== '';
}
function initContextMenu() {
  document.addEventListener('contextmenu', (event) => {
    if (!(event.target instanceof Element)) return;
    if (shouldUseBrowserContextMenu(event.target)) {
      hideContextMenu();
      return;
    }
    const source = document.querySelector('.header-nav .dropdown-content');
    if (!(source instanceof HTMLElement)) return;
    event.preventDefault();
    closeDropdowns();
    hideContextMenu();
    const menu = source.cloneNode(true);
    if (!(menu instanceof HTMLElement)) return;
    menu.style.position = 'fixed';
    menu.style.left = '0px';
    menu.style.top = '0px';
    menu.style.right = 'auto';
    document.body.append(menu);
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    const menuWidth = menu.offsetWidth;
    const menuHeight = menu.offsetHeight;
    const edgePadding = 8;
    const opensLeft = event.clientX + menuWidth > viewportWidth - edgePadding;
    const opensUp = event.clientY + menuHeight > viewportHeight - edgePadding;
    const rawX = opensLeft ? event.clientX - menuWidth : event.clientX;
    const rawY = opensUp ? event.clientY - menuHeight : event.clientY;
    const maxX = Math.max(0, viewportWidth - menuWidth);
    const maxY = Math.max(0, viewportHeight - menuHeight);
    const posX = Math.max(0, Math.min(rawX, maxX));
    const posY = Math.max(0, Math.min(rawY, maxY));
    menu.style.left = `${posX}px`;
    menu.style.top = `${posY}px`;
    contextMenu = menu;
  });
  document.addEventListener('click', hideContextMenu);
  document.addEventListener('scroll', hideContextMenu, { passive: true });
  window.addEventListener('resize', hideContextMenu);
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      hideContextMenu();
    }
  });
}
function initGlobalShortcuts() {
  const pageId = document.body?.id || '';
  document.addEventListener('keydown', (e) => {
    const mod = e.metaKey || e.ctrlKey;
    const isTyping = isTypingTarget(e.target);
    if (e.repeat) return;
    if (e.key === '/' && !isTyping && !mod) {
      e.preventDefault();
      const searchInput = document.getElementById('search');
      if (searchInput) {
        searchInput.focus();
        searchInput.select();
      } else {
        navigateToShortcut('search');
      }
    }
    if (e.key === 'e' && !isTyping && !mod && pageId === 'view') {
      e.preventDefault();
      navigateToShortcut('edit');
    }
    if (e.key === 'n' && !isTyping && !mod && pageId !== 'edit' && pageId !== 'new') {
      e.preventDefault();
      navigateToShortcut('new');
    }
  });
}
let wikiAutocompleteTitlesCache = null;
let wikiAutocompleteTitlesPromise = null;
function loadWikiAutocompleteTitles() {
  if (Array.isArray(wikiAutocompleteTitlesCache)) {
    return Promise.resolve(wikiAutocompleteTitlesCache);
  }
  if (wikiAutocompleteTitlesPromise instanceof Promise) {
    return wikiAutocompleteTitlesPromise;
  }
  const searchShortcut = document.querySelector('a[data-shortcut="search"]');
  const basePath = searchShortcut instanceof HTMLAnchorElement
    ? new URL(searchShortcut.href, window.location.href).pathname.replace(/\/search$/, '')
    : '';
  wikiAutocompleteTitlesPromise = fetch(`${basePath}/api/all`, { headers: { Accept: 'application/json' } })
    .then((response) => {
      if (!response.ok) return { pages: [] };
      return response.json();
    })
    .then((payload) => {
      const pages = Array.isArray(payload?.pages) ? payload.pages : [];
      const titles = [];
      const seen = new Set();
      pages.forEach((page) => {
        const title = typeof page?.title === 'string' ? page.title.trim() : '';
        if (title === '' || seen.has(title)) return;
        seen.add(title);
        titles.push(title);
      });
      titles.sort((a, b) => a.localeCompare(b, undefined, { sensitivity: 'base' }));
      wikiAutocompleteTitlesCache = titles;
      return titles;
    })
    .catch(() => {
      wikiAutocompleteTitlesCache = [];
      return [];
    })
    .finally(() => {
      wikiAutocompleteTitlesPromise = null;
    });
  return wikiAutocompleteTitlesPromise;
}
function wikiLinkMatchAtCursor(value, cursorStart, cursorEnd) {
  if (cursorStart !== cursorEnd) return null;
  const prefix = value.slice(0, cursorStart);
  let openIndex = prefix.lastIndexOf('[[');
  if (openIndex < 0) return null;
  if (prefix.lastIndexOf(']]') > openIndex) return null;
  let bracketRunStart = openIndex;
  while (bracketRunStart > 0 && prefix[bracketRunStart - 1] === '[') {
    bracketRunStart -= 1;
  }
  if (bracketRunStart < openIndex) {
    openIndex = bracketRunStart;
  }
  const rawQuery = prefix.slice(openIndex + 2);
  const query = rawQuery.replace(/^\[+/, '');
  if (query.includes('\n') || query.includes('\r') || query.includes('|') || query.includes(']')) return null;
  return { openIndex, query };
}
function textareaCaretRect(textarea, caretIndex) {
  const computed = window.getComputedStyle(textarea);
  const mirror = document.createElement('div');
  const copiedStyles = [
    'boxSizing', 'width',
    'paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft',
    'borderTopWidth', 'borderRightWidth', 'borderBottomWidth', 'borderLeftWidth',
    'fontFamily', 'fontSize', 'fontWeight', 'fontStyle',
    'lineHeight', 'letterSpacing', 'textTransform', 'textIndent',
    'tabSize', 'wordSpacing', 'textRendering',
  ];
  copiedStyles.forEach((s) => { mirror.style[s] = computed[s]; });
  Object.assign(mirror.style, {
    position: 'fixed', left: '0', top: '0',
    visibility: 'hidden', pointerEvents: 'none',
    whiteSpace: 'pre-wrap', wordBreak: 'break-word', overflow: 'hidden',
  });
  mirror.textContent = textarea.value.slice(0, Math.max(0, caretIndex));
  const marker = document.createElement('span');
  marker.textContent = '\u200b';
  mirror.append(marker);
  document.body.append(mirror);
  const mirrorRect = mirror.getBoundingClientRect();
  const markerRect = marker.getBoundingClientRect();
  const textareaRect = textarea.getBoundingClientRect();
  const lineHeight = Number.parseFloat(computed.lineHeight);
  mirror.remove();
  return {
    top: textareaRect.top + (markerRect.top - mirrorRect.top) - textarea.scrollTop,
    left: textareaRect.left + (markerRect.left - mirrorRect.left) - textarea.scrollLeft,
    height: Number.isFinite(lineHeight) ? lineHeight : Math.max(markerRect.height, 16),
  };
}
function positionPanel(panel, textarea, caretIndex) {
  if (!(panel instanceof HTMLElement) || !(textarea instanceof HTMLTextAreaElement)) return;
  if (panel.hidden) return;
  const caret = textareaCaretRect(textarea, caretIndex);
  const textareaRect = textarea.getBoundingClientRect();
  const vp = 8;
  const gap = 6;
  const w = Math.round(Math.min(Math.max(180, textareaRect.width), 420));
  panel.style.width = `${w}px`;
  const panelH = panel.offsetHeight;
  const left = Math.max(vp, Math.min(Math.round(caret.left), window.innerWidth - w - vp));
  let top = caret.top + caret.height + gap;
  if (top + panelH > window.innerHeight - vp) {
    top = caret.top - panelH - gap;
  }
  top = Math.max(vp, Math.min(top, window.innerHeight - panelH - vp));
  panel.style.left = `${left}px`;
  panel.style.top = `${Math.round(top)}px`;
}
function shouldDisableWikiAutocompleteOnDevice() {
  if (typeof window !== 'undefined' && typeof window.matchMedia === 'function') {
    if (window.matchMedia('(pointer: coarse)').matches) return true;
    if (window.matchMedia('(hover: none)').matches) return true;
    return false;
  }
  const maxTouchPoints = (typeof navigator !== 'undefined' && typeof navigator.maxTouchPoints === 'number')
    ? navigator.maxTouchPoints
    : 0;
  if (maxTouchPoints > 0) return true;
  const ua = (typeof navigator !== 'undefined' && typeof navigator.userAgent === 'string')
    ? navigator.userAgent
    : '';
  return /Android|iPhone|iPad|iPod|Mobile/i.test(ua);
}
function initWikiLinkAutocomplete(textarea) {
  if (!(textarea instanceof HTMLTextAreaElement)) return;
  if (typeof fetch !== 'function') return;
  if (shouldDisableWikiAutocompleteOnDevice()) return;
  const panel = document.createElement('div');
  panel.className = 'autocomplete';
  panel.hidden = true;
  panel.setAttribute('role', 'listbox');
  document.body.append(panel);
  let options = [];
  let activeIndex = -1;
  let activeMatch = null;
  let requestToken = 0;
  function closePanel() {
    panel.hidden = true;
    panel.replaceChildren();
    options = [];
    activeIndex = -1;
    activeMatch = null;
  }
  function selectOption(index) {
    if (!(index >= 0 && index < options.length) || activeMatch === null) return;
    const selectedTitle = options[index];
    const value = textarea.value;
    const cursor = textarea.selectionStart ?? 0;
    const insertStart = activeMatch.openIndex + 2;
    const replacement = `${selectedTitle}]]`;
    textarea.value = value.slice(0, insertStart) + replacement + value.slice(cursor);
    const nextCursor = insertStart + replacement.length;
    textarea.setSelectionRange(nextCursor, nextCursor);
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
    textarea.focus();
    closePanel();
  }
  function renderOptions() {
    panel.hidden = options.length === 0;
    if (panel.hidden) return;
    panel.replaceChildren();
    options.forEach((title, index) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'autocomplete-item';
      button.setAttribute('role', 'option');
      button.setAttribute('aria-selected', index === activeIndex ? 'true' : 'false');
      button.textContent = title;
      button.addEventListener('mousedown', (e) => e.preventDefault());
      button.addEventListener('click', () => selectOption(index));
      panel.append(button);
    });
    positionPanel(panel, textarea, textarea.selectionStart ?? 0);
    const activeBtn = panel.children[activeIndex];
    if (activeBtn instanceof Element) {
      activeBtn.scrollIntoView({ block: 'nearest' });
    }
  }
  function updateOptions() {
    const cursorStart = textarea.selectionStart ?? 0;
    const cursorEnd = textarea.selectionEnd ?? cursorStart;
    const match = wikiLinkMatchAtCursor(textarea.value, cursorStart, cursorEnd);
    if (match === null) {
      closePanel();
      return;
    }
    activeMatch = match;
    const token = ++requestToken;
    loadWikiAutocompleteTitles().then((titles) => {
      if (token !== requestToken) return;
      const latestStart = textarea.selectionStart ?? 0;
      const latestEnd = textarea.selectionEnd ?? latestStart;
      const latestMatch = wikiLinkMatchAtCursor(textarea.value, latestStart, latestEnd);
      if (latestMatch === null) {
        closePanel();
        return;
      }
      activeMatch = latestMatch;
      const query = latestMatch.query.trim().toLowerCase();
      const filtered = query === ''
        ? titles
        : titles.filter((title) => title.toLowerCase().includes(query));
      options = filtered.slice(0, 10);
      if (options.length === 0) {
        closePanel();
        return;
      }
      if (activeIndex < 0 || activeIndex >= options.length) {
        activeIndex = 0;
      }
      renderOptions();
    });
  }
  textarea.addEventListener('input', (event) => {
    if (event.isComposing) return;
    updateOptions();
  });
  textarea.addEventListener('click', updateOptions);
  textarea.addEventListener('keyup', (event) => {
    if (event.isComposing) return;
    if (event.key === 'ArrowUp' || event.key === 'ArrowDown' || event.key === 'Escape') return;
    updateOptions();
  });
  const reposition = () => positionPanel(panel, textarea, textarea.selectionStart ?? 0);
  textarea.addEventListener('scroll', reposition);
  window.addEventListener('resize', reposition);
  document.addEventListener('scroll', reposition, true);
  textarea.addEventListener('keydown', (event) => {
    if (panel.hidden) return;
    if (event.isComposing) return;
    if (event.key === 'ArrowDown') {
      event.preventDefault();
      event.stopImmediatePropagation();
      activeIndex = (activeIndex + 1) % options.length;
      renderOptions();
      return;
    }
    if (event.key === 'ArrowUp') {
      event.preventDefault();
      event.stopImmediatePropagation();
      activeIndex = (activeIndex - 1 + options.length) % options.length;
      renderOptions();
      return;
    }
    if (event.key === 'Enter' || event.key === 'Tab') {
      event.preventDefault();
      event.stopImmediatePropagation();
      if (activeIndex < 0 && options.length > 0) {
        activeIndex = 0;
      }
      selectOption(activeIndex);
      return;
    }
  }, true);
  document.addEventListener('keydown', (event) => {
    if (panel.hidden) return;
    if (event.key !== 'Escape') return;
    event.preventDefault();
    event.stopImmediatePropagation();
    closePanel();
    textarea.focus();
  }, true);
  const closeIfOutside = (event) => {
    const target = event.target;
    if (!(target instanceof Node)) return;
    if (target === textarea || textarea.contains(target) || panel.contains(target)) return;
    closePanel();
  };
  document.addEventListener('mousedown', closeIfOutside);
}
function initEditMode() {
  const pageId = document.body?.id || '';
  if (pageId !== 'edit' && pageId !== 'new') return;
  const form = document.getElementById('form');
  const textarea = document.getElementById('content');
  const saveButton = document.getElementById('button-save');
  if (!(form instanceof HTMLFormElement) || !(textarea instanceof HTMLTextAreaElement) || !(saveButton instanceof HTMLButtonElement)) return;
  const cancelButton = document.getElementById('button-cancel');
  let originalValue = textarea.value;
  let beforeUnloadBound = false;
  let isSubmitting = false;
  initWikiLinkAutocomplete(textarea);
  const setSaveButtonSubmitting = (submitting) => {
    saveButton.disabled = submitting;
    saveButton.setAttribute('aria-disabled', submitting ? 'true' : 'false');
  };
  const resetSubmitState = () => {
    isSubmitting = false;
    setSaveButtonSubmitting(false);
  };
  const handleBeforeUnload = (event) => {
    if (textarea.value === originalValue) return;
    event.preventDefault();
    event.returnValue = '';
  };
  const syncBeforeUnloadListener = () => {
    const isDirty = textarea.value !== originalValue;
    if (isDirty && !beforeUnloadBound) {
      window.addEventListener('beforeunload', handleBeforeUnload);
      beforeUnloadBound = true;
    } else if (!isDirty && beforeUnloadBound) {
      window.removeEventListener('beforeunload', handleBeforeUnload);
      beforeUnloadBound = false;
    }
  };
  const markEditorClean = () => {
    originalValue = textarea.value;
    syncBeforeUnloadListener();
  };
  form.addEventListener('submit', (event) => {
    if (isSubmitting) {
      event.preventDefault();
      return;
    }
    isSubmitting = true;
    markEditorClean();
    setSaveButtonSubmitting(true);
  });
  textarea.addEventListener('input', () => {
    syncBeforeUnloadListener();
    if (isSubmitting) {
      resetSubmitState();
    }
  });
  window.addEventListener('pageshow', resetSubmitState);
  if (cancelButton instanceof HTMLElement) {
    cancelButton.addEventListener('click', () => {
      markEditorClean();
      resetSubmitState();
    });
  }
  saveButton.addEventListener('click', (event) => {
    if (isSubmitting || saveButton.disabled) {
      event.preventDefault();
      return;
    }
    if (!confirmDeleteIfEmpty(saveButton)) {
      event.preventDefault();
    }
  });
  function submitEditorFromShortcut() {
    if (isSubmitting || saveButton.disabled) return;
    if (!confirmDeleteIfEmpty(saveButton)) return;
    markEditorClean();
    if (typeof form.requestSubmit === 'function') {
      form.requestSubmit(saveButton);
    } else {
      isSubmitting = true;
      setSaveButtonSubmitting(true);
      form.submit();
    }
  }
  function applyMarkdownWrap(prefix, suffix) {
    const value = textarea.value;
    const start = textarea.selectionStart ?? 0;
    const end = textarea.selectionEnd ?? start;
    const selected = value.slice(start, end);
    const replacement = `${prefix}${selected}${suffix}`;
    textarea.focus();
    textarea.setSelectionRange(start, end);
    const usedExecCommand = typeof document.execCommand === 'function'
      && document.execCommand('insertText', false, replacement);
    if (!usedExecCommand) {
      textarea.setRangeText(replacement, start, end, 'end');
    }
    if (selected === '') {
      const cursorPos = start + prefix.length;
      textarea.setSelectionRange(cursorPos, cursorPos);
    } else {
      const innerStart = start + prefix.length;
      const innerEnd = innerStart + selected.length;
      textarea.setSelectionRange(innerStart, innerEnd);
    }
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
  }
  function lineStartIndex(value, index) {
    return value.lastIndexOf('\n', Math.max(0, index - 1)) + 1;
  }
  function indentSelectedLines() {
    const indentUnit = '  ';
    const value = textarea.value;
    const start = textarea.selectionStart ?? 0;
    const end = textarea.selectionEnd ?? start;
    if (start === end) {
      textarea.setRangeText(indentUnit, start, end, 'end');
      textarea.dispatchEvent(new Event('input', { bubbles: true }));
      return;
    }
    const selectionStart = lineStartIndex(value, start);
    const selectedBlock = value.slice(selectionStart, end);
    const lines = selectedBlock.split('\n');
    const replacement = lines.map(line => `${indentUnit}${line}`).join('\n');
    textarea.setRangeText(replacement, selectionStart, end, 'preserve');
    const nextStart = start + indentUnit.length;
    const nextEnd = end + indentUnit.length * lines.length;
    textarea.setSelectionRange(nextStart, nextEnd);
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
  }
  function outdentSelectedLines() {
    const indentUnit = '  ';
    const value = textarea.value;
    const start = textarea.selectionStart ?? 0;
    const end = textarea.selectionEnd ?? start;
    const selectionStart = lineStartIndex(value, start);
    const selectedBlock = value.slice(selectionStart, end);
    const lines = selectedBlock.split('\n');
    let removedFromFirstLine = 0;
    let removedTotal = 0;
    const replacement = lines.map((line, index) => {
      let removed = 0;
      if (line.startsWith(indentUnit)) {
        removed = indentUnit.length;
      } else if (line.startsWith('\t')) {
        removed = 1;
      }
      if (index === 0) {
        removedFromFirstLine = removed;
      }
      removedTotal += removed;
      return line.slice(removed);
    }).join('\n');
    if (removedTotal === 0) return;
    textarea.setRangeText(replacement, selectionStart, end, 'preserve');
    const nextStart = Math.max(selectionStart, start - removedFromFirstLine);
    const nextEnd = Math.max(nextStart, end - removedTotal);
    textarea.setSelectionRange(nextStart, nextEnd);
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
  }
  textarea.addEventListener('keydown', (event) => {
    if (event.isComposing) return;
    if (event.key === 'Tab' && !event.metaKey && !event.ctrlKey && !event.altKey) {
      event.preventDefault();
      if (event.shiftKey) {
        outdentSelectedLines();
      } else {
        indentSelectedLines();
      }
      return;
    }
    const mod = event.metaKey || event.ctrlKey;
    if (!mod || event.altKey) return;
    const key = event.key.toLowerCase();
    if (key === 'b') {
      event.preventDefault();
      applyMarkdownWrap('**', '**');
      return;
    }
    if (key === 'i') {
      event.preventDefault();
      applyMarkdownWrap('*', '*');
      return;
    }
    if (key === 'k') {
      event.preventDefault();
      applyMarkdownWrap('[[', ']]');
    }
  });
  document.addEventListener('keydown', (e) => {
    if (e.isComposing) return;
    if (e.repeat) return;
    const mod = e.metaKey || e.ctrlKey;
    if (mod && !e.altKey) {
      const key = e.key.toLowerCase();
      if (key === 's' || e.key === 'Enter') {
        e.preventDefault();
        submitEditorFromShortcut();
        return;
      }
    }
  });
  resetSubmitState();
  syncBeforeUnloadListener();
}
function initDataTabs() {
  const tabLists = Array.from(document.querySelectorAll('.tabs'));
  if (tabLists.length === 0) return;
  tabLists.forEach((tabList) => {
    const tabs = Array.from(tabList.querySelectorAll('.tab[data-target]'));
    if (tabs.length === 0) return;
    tabList.setAttribute('role', 'tablist');
    const container = tabList.parentElement;
    if (!container) return;
    const panes = Array.from(container.querySelectorAll(':scope > .tab-content[data-id]'));
    if (panes.length === 0) return;
    const paneIds = new Set(panes.map((pane) => (pane.dataset.id || '').trim()).filter(Boolean));
    const tabGroupId = tabList.id || `tabs-${Math.random().toString(36).slice(2, 8)}`;
    if (!tabList.id) {
      tabList.id = tabGroupId;
    }
    const defaultTarget = tabs
      .map((tab) => (tab.dataset.target || '').trim())
      .find((target) => paneIds.has(target)) || (panes[0].dataset.id || '').trim();
    const queryKey = (tabList.dataset.queryKey || '').trim();
    const queryTarget = queryKey === ''
      ? ''
      : ((new URLSearchParams(window.location.search).get(queryKey) || '').trim());
    function targetToPaneId(target) {
      return `panel-${target.replace(/[^a-zA-Z0-9_-]/g, '-')}`;
    }
    function syncTabQuery(activeTarget) {
      if (queryKey === '' || !paneIds.has(activeTarget)) return;
      const nextUrl = new URL(window.location.href);
      if (nextUrl.searchParams.get(queryKey) === activeTarget) return;
      nextUrl.searchParams.set(queryKey, activeTarget);
      window.history.replaceState(window.history.state, '', nextUrl.toString());
    }
    tabs.forEach((tab, index) => {
      const target = (tab.dataset.target || '').trim();
      tab.setAttribute('role', 'tab');
      if (!tab.id) {
        tab.id = `${tabGroupId}-tab-${index + 1}`;
      }
      if (paneIds.has(target)) {
        tab.setAttribute('aria-controls', targetToPaneId(target));
      }
    });
    panes.forEach((pane) => {
      const paneTarget = (pane.dataset.id || '').trim();
      pane.id = targetToPaneId(paneTarget);
      pane.setAttribute('role', 'tabpanel');
    });
    function renderTab(target, syncUrl = false) {
      const activeTarget = paneIds.has(target) ? target : defaultTarget;
      panes.forEach((pane) => {
        const active = (pane.dataset.id || '').trim() === activeTarget;
        pane.hidden = !active;
        if (active) {
          const activeTab = tabs.find((tab) => (tab.dataset.target || '').trim() === activeTarget);
          if (activeTab) {
            pane.setAttribute('aria-labelledby', activeTab.id);
          }
        }
      });
      tabs.forEach((tab) => {
        const active = (tab.dataset.target || '').trim() === activeTarget;
        tab.setAttribute('aria-selected', active ? 'true' : 'false');
        tab.tabIndex = active ? 0 : -1;
      });
      if (syncUrl) {
        syncTabQuery(activeTarget);
      }
    }
    const initialTarget = paneIds.has(queryTarget)
      ? queryTarget
      : (tabs.find((tab) => tab.getAttribute('aria-selected') === 'true')?.dataset.target || defaultTarget);
    renderTab((initialTarget || '').trim(), false);
    tabs.forEach((tab) => {
      tab.addEventListener('click', () => {
        renderTab((tab.dataset.target || '').trim(), true);
      });
      tab.addEventListener('keydown', (event) => {
        const currentIndex = tabs.indexOf(tab);
        if (currentIndex < 0) return;
        let nextIndex = -1;
        if (event.key === 'ArrowRight') {
          nextIndex = (currentIndex + 1) % tabs.length;
        } else if (event.key === 'ArrowLeft') {
          nextIndex = (currentIndex - 1 + tabs.length) % tabs.length;
        } else if (event.key === 'Home') {
          nextIndex = 0;
        } else if (event.key === 'End') {
          nextIndex = tabs.length - 1;
        }
        if (nextIndex >= 0) {
          event.preventDefault();
          const nextTab = tabs[nextIndex];
          renderTab((nextTab.dataset.target || '').trim(), true);
          nextTab.focus();
        }
      });
    });
  });
}
initFlashAutoHide();
initUsernameInputFilter();
initSubmitConfirmHandler();
initDropdownAutoClose();
initContextMenu();
initGlobalShortcuts();
initEditMode();
initDataTabs();
