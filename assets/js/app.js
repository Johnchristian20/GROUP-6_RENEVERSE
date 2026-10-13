document.addEventListener('DOMContentLoaded', () => {
  // SHOW / HIDE PASSWORD
  const toggle = document.getElementById('togglePassword');
  const password = document.getElementById('password');

  if (toggle && password) {
    toggle.addEventListener('click', () => {
      const reveal = password.type === 'password';
      password.type = reveal ? 'text' : 'password';
      toggle.textContent = reveal ? 'Hide' : 'Show';
      toggle.setAttribute(
        'aria-label',
        reveal ? 'Hide password' : 'Show password'
      );
    });
  }

  // SIDEBAR COLLAPSE
  const body = document.body;
  const sidebarButton = document.getElementById('sidebarToggle');

  if (sidebarButton) {
    const saved = localStorage.getItem('readingRoomSidebarCollapsed');

    if (saved === 'true') {
      body.classList.add('sidebar-collapsed');
    }

    const syncButton = () => {
      const collapsed = body.classList.contains('sidebar-collapsed');

      sidebarButton.setAttribute('aria-expanded', String(!collapsed));
      sidebarButton.setAttribute(
        'aria-label',
        collapsed ? 'Expand sidebar' : 'Minimize sidebar'
      );
      sidebarButton.setAttribute(
        'title',
        collapsed ? 'Expand sidebar' : 'Minimize sidebar'
      );
      sidebarButton.textContent = collapsed ? '›' : '‹';
    };

    syncButton();

    sidebarButton.addEventListener('click', () => {
      body.classList.toggle('sidebar-collapsed');

      localStorage.setItem(
        'readingRoomSidebarCollapsed',
        String(body.classList.contains('sidebar-collapsed'))
      );

      syncButton();
    });
  }

  // THEMED CONFIRMATION DIALOG
  let activeDialog = null;

  function showConfirmation(options) {
    return new Promise((resolve) => {
      if (activeDialog) {
        resolve(false);
        return;
      }

      const {
        title = 'Are you sure?',
        message = 'Do you want to continue?',
        action = 'Confirm',
        danger = false
      } = options;

      const overlay = document.createElement('div');
      overlay.className = 'rr-dialog-overlay';

      const dialog = document.createElement('section');
      dialog.className = 'rr-dialog';
      dialog.setAttribute('role', 'alertdialog');
      dialog.setAttribute('aria-modal', 'true');
      dialog.setAttribute('aria-labelledby', 'rr-dialog-title');
      dialog.setAttribute('aria-describedby', 'rr-dialog-message');

      const icon = document.createElement('div');
      icon.className = `rr-dialog-icon${danger ? ' is-danger' : ''}`;
      icon.setAttribute('aria-hidden', 'true');
      icon.textContent = danger ? '!' : '?';

      const heading = document.createElement('h2');
      heading.className = 'rr-dialog-title';
      heading.id = 'rr-dialog-title';
      heading.textContent = title;

      const description = document.createElement('p');
      description.className = 'rr-dialog-message';
      description.id = 'rr-dialog-message';
      description.textContent = message;

      const actions = document.createElement('div');
      actions.className = 'rr-dialog-actions';

      const cancelButton = document.createElement('button');
      cancelButton.type = 'button';
      cancelButton.className = 'button button-outline';
      cancelButton.textContent = 'Cancel';

      const confirmButton = document.createElement('button');
      confirmButton.type = 'button';
      confirmButton.className =
        `button ${danger ? 'rr-button-danger' : 'button-primary'}`;
      confirmButton.textContent = action;

      actions.append(cancelButton, confirmButton);
      dialog.append(icon, heading, description, actions);
      overlay.appendChild(dialog);
      document.body.appendChild(overlay);

      activeDialog = overlay;
      document.body.classList.add('rr-dialog-open');

      let finished = false;

      function close(result) {
        if (finished) return;
        finished = true;

        document.removeEventListener('keydown', handleKeydown);
        overlay.remove();
        document.body.classList.remove('rr-dialog-open');
        activeDialog = null;

        resolve(result);
      }

      function handleKeydown(event) {
        if (event.key === 'Escape') {
          event.preventDefault();
          close(false);
        }

        if (event.key === 'Tab') {
          event.preventDefault();

          if (document.activeElement === cancelButton) {
            confirmButton.focus();
          } else {
            cancelButton.focus();
          }
        }
      }

      cancelButton.addEventListener('click', () => close(false));
      confirmButton.addEventListener('click', () => close(true));

      overlay.addEventListener('click', (event) => {
        if (event.target === overlay) close(false);
      });

      document.addEventListener('keydown', handleKeydown);
      cancelButton.focus();
    });
  }


  // CONFIRM LOGOUT
  document.querySelectorAll('a[data-confirm-logout="true"]').forEach((link) => {
    link.addEventListener('click', async (event) => {
      event.preventDefault();

      const confirmed = await showConfirmation({
        title: 'Leaving The Reading Room?',
        message: 'Are you sure you want to log out of your account?',
        action: 'Yes, log out',
        danger: true
      });

      if (confirmed) {
        window.location.href = link.href;
      }
    });
  });


  // CONFIRM ADD, EDIT, AND DELETE FORMS
  document.querySelectorAll('form[data-confirm-message]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
      // Allow the submission after the user confirms.
      if (form.dataset.confirmed === 'true') {
        delete form.dataset.confirmed;
        return;
      }

      event.preventDefault();

      const submitButton = event.submitter;

      const confirmed = await showConfirmation({
        title: form.dataset.confirmTitle || 'Please confirm',
        message: form.dataset.confirmMessage || 'Do you want to continue?',
        action: form.dataset.confirmAction || 'Confirm',
        danger: form.dataset.confirmDanger === 'true'
      });

      if (!confirmed) return;

      form.dataset.confirmed = 'true';

      if (submitButton && submitButton.form === form) {
        form.requestSubmit(submitButton);
      } else {
        form.requestSubmit();
      }
    });
  });
});


