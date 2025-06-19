/**
 * Emergency fix to remove conflicting chatbot and ensure proper positioning
 */
(function() {
  'use strict';
  
  // Run immediately when script loads
  function removeConflictingChatbot() {
    // Find all df-messenger elements
    const allChatbots = document.querySelectorAll('df-messenger');
    console.log('Total chatbots found:', allChatbots.length);
    
    allChatbots.forEach((chatbot, index) => {
      console.log(`Chatbot ${index}:`, {
        hasAgentId: chatbot.hasAttribute('agent-id'),
        agentId: chatbot.getAttribute('agent-id'),
        className: chatbot.className,
        parent: chatbot.parentElement?.className
      });
      
      // Remove any chatbot without our custom class or without agent-id
      if (!chatbot.classList.contains('ilas-custom-chatbot') || !chatbot.getAttribute('agent-id')) {
        console.log('Removing chatbot:', chatbot);
        chatbot.remove();
      }
    });
    
    // Also remove any floating elements that might be the black circle
    const floatingElements = document.querySelectorAll('[style*="position: fixed"][style*="right"]');
    floatingElements.forEach(el => {
      if (el.tagName === 'DF-MESSENGER' || el.querySelector('df-messenger')) {
        console.log('Removing floating element:', el);
        el.remove();
      }
    });
  }
  
  // Run on page load
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', removeConflictingChatbot);
  } else {
    removeConflictingChatbot();
  }
  
  // Run again after a delay to catch dynamically added elements
  setTimeout(removeConflictingChatbot, 1000);
  setTimeout(removeConflictingChatbot, 3000);
})();