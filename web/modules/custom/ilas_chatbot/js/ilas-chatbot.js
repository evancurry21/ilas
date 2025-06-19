/**
 * ILAS Chatbot Integration
 * Advanced Dialogflow Messenger implementation
 */
(function (Drupal, drupalSettings, once) {
  'use strict';

  // Configuration from Drupal settings
  const config = {
    agentId: drupalSettings.ilasChatbot?.agentId || '',
    languageCode: drupalSettings.ilasChatbot?.languageCode || 'en',
    welcomeIntent: drupalSettings.ilasChatbot?.welcomeIntent || 'WELCOME',
    formMappings: drupalSettings.ilasChatbot?.formMappings || {}
  };

  // Device detection utility
  const isMobileDevice = () => {
    return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) || 
           window.innerWidth <= 768;
  };

  // Initialize chatbot when DOM is ready
  Drupal.behaviors.ilasChatbot = {
    attach: function (context) {
      once('ilas-chatbot-init', 'body', context).forEach(() => {
        // Remove any existing chatbots that aren't ours
        const existingChatbots = document.querySelectorAll('df-messenger:not(.ilas-custom-chatbot)');
        console.log('Existing chatbots found:', existingChatbots.length);
        
        existingChatbots.forEach(chatbot => {
          console.log('Removing existing chatbot:', chatbot);
          chatbot.remove();
        });
        
        // Check if our chatbot already exists
        if (document.querySelector('df-messenger.ilas-custom-chatbot')) {
          console.log('ILAS chatbot already initialized');
          return;
        }
        
        initializeChatbot();
      });
    }
  };

  function initializeChatbot() {
    // Create chatbot container
    const container = document.createElement('div');
    container.className = 'ilas-chatbot-container';
    container.innerHTML = `
      <df-messenger
        class="ilas-custom-chatbot"
        agent-id="${config.agentId}"
        language-code="${config.languageCode}"
        intent="${config.welcomeIntent}"
        chat-title="Legal Assistant"
        chat-icon="${getCustomIcon()}"
        expand="false">
        <df-messenger-chat-bubble></df-messenger-chat-bubble>
      </df-messenger>
      <div id="ilas-form-container" class="ilas-form-container hidden"></div>
    `;
    document.body.appendChild(container);

    // Listen for Dialogflow events
    window.addEventListener('dfMessengerLoaded', handleMessengerLoaded);
    window.addEventListener('df-response-received', handleResponse);
    window.addEventListener('df-chip-clicked', handleChipClick);
  }

  function handleMessengerLoaded() {
    const dfMessenger = document.querySelector('df-messenger');
    
    if (!dfMessenger) return;

    // Apply responsive styling
    applyResponsiveStyling(dfMessenger);
    
    // Set up mutation observer for dynamic content
    setupMutationObserver(dfMessenger);
  }

  function applyResponsiveStyling(dfMessenger) {
    const isMobile = isMobileDevice();
    
    // Inject custom styles into shadow DOM
    const style = document.createElement('style');
    style.textContent = `
      df-messenger-chat {
        width: ${isMobile ? '90vw' : '450px'} !important;
        height: ${isMobile ? '80vh' : '600px'} !important;
        max-height: ${isMobile ? '100vh' : '80vh'} !important;
        left: ${isMobile ? '0' : '20px'} !important;
        right: auto !important;
      }
      
      .chat-wrapper {
        height: 100% !important;
      }
      
      /* FAB button styling */
      .df-chat-open-icon {
        background-color: var(--bs-primary, #0d6efd) !important;
      }
      
      @media (max-width: 768px) {
        df-messenger-chat {
          bottom: 0 !important;
          left: 0 !important;
          border-radius: 0 !important;
        }
      }
    `;
    
    // Access shadow DOM if available
    if (dfMessenger.shadowRoot) {
      dfMessenger.shadowRoot.appendChild(style);
    }
  }

  function setupMutationObserver(dfMessenger) {
    const observer = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        if (mutation.type === 'childList') {
          handleDynamicContent();
        }
      });
    });
    
    observer.observe(dfMessenger, {
      childList: true,
      subtree: true
    });
  }

  function handleResponse(event) {
    const { response } = event.detail;
    
    // Check for form triggers in response
    if (response?.queryResult?.parameters?.formType) {
      loadForm(response.queryResult.parameters.formType);
    }
  }

  function handleChipClick(event) {
    const chipText = event.detail?.text;
    
    // Map chip text to form types
    const formMapping = {
      'File for Eviction': 'eviction',
      'Divorce/Custody': 'divorce',
      'Benefits Appeal': 'benefits',
      'Small Claims': 'small_claims',
      // Add more mappings
    };
    
    if (formMapping[chipText]) {
      loadForm(formMapping[chipText]);
    }
  }

  function loadForm(formType) {
    const formContainer = document.getElementById('ilas-form-container');
    const formUrl = config.formMappings[formType];
    
    if (!formUrl || !formContainer) return;
    
    // Show loading state
    formContainer.innerHTML = '<div class="loading">Loading form...</div>';
    formContainer.classList.remove('hidden');
    
    // Create iframe for form
    const iframe = document.createElement('iframe');
    iframe.src = formUrl;
    iframe.className = 'ilas-form-iframe';
    iframe.onload = () => {
      formContainer.innerHTML = '';
      formContainer.appendChild(iframe);
      
      // Add close button
      const closeBtn = document.createElement('button');
      closeBtn.className = 'ilas-form-close';
      closeBtn.innerHTML = '&times;';
      closeBtn.onclick = () => {
        formContainer.classList.add('hidden');
        formContainer.innerHTML = '';
      };
      formContainer.appendChild(closeBtn);
    };
  }

  function handleDynamicContent() {
    // Handle any dynamic content updates
    const chatWindow = document.querySelector('df-messenger-chat');
    if (chatWindow && isMobileDevice()) {
      // Remove elements that don't work well on mobile
      const minimizeBtn = chatWindow.shadowRoot?.querySelector('.minimize-button');
      if (minimizeBtn) {
        minimizeBtn.style.display = 'none';
      }
    }
  }

  function getCustomIcon() {
    // Return base64 encoded SVG icon
    return 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPGNpcmNsZSBjeD0iMzAiIGN5PSIzMCIgcj0iMzAiIGZpbGw9IiMxOTc2RDIiLz4KPHBhdGggZD0iTTMwIDEwQzE5LjUgMTAgMTEgMTcuNSAxMSAyNi41QzExIDMyLjUgMTQuNSAzNy41IDE5LjUgNDAuNUwxOCA0OEwzMCA0M0M0MC41IDQzIDQ5IDM1LjUgNDkgMjYuNUM0OSAxNy41IDQwLjUgMTAgMzAgMTBaIiBmaWxsPSJ3aGl0ZSIvPgo8L3N2Zz4=';
  }

})(Drupal, drupalSettings, once);