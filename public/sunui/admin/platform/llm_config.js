$(document).ready(function () {

  // ============================================================
  // Provider endpoint auto-fill
  // ============================================================
  var PROVIDER_ENDPOINTS = {
    'openai': 'https://api.openai.com/v1',
    'anthropic': 'https://api.anthropic.com',
    'azure': '',
    'ollama': 'http://localhost:11434',
    'lmstudio': 'http://localhost:1234/v1',
    'deepseek': 'https://api.deepseek.com',
    'moonshot': 'https://api.moonshot.cn/v1',
    'qwen': 'https://dashscope.aliyuncs.com/compatible-mode/v1',
    'glm': 'https://open.bigmodel.cn/api/paas/v4',
    'ernie': 'https://aip.baidubce.com',
    'doubao': 'https://ark.cn-beijing.volces.com/api/v3',
    'baichuan': 'https://api.baichuan-ai.com/v1',
    'yi': 'https://api.lingyiwanwu.com/v1',
    'siliconflow': 'https://api.siliconflow.cn/v1',
    'custom': '',
  };

  var PROVIDER_MODEL_HINTS = {
    'openai': 'gpt-4o',
    'anthropic': 'claude-3-5-sonnet-20241022',
    'ollama': 'qwen2.5-coder',
    'lmstudio': 'QuantFactory/Qwen2.5-7B-Instruct-GGUF',
    'azure': 'gpt-4o',
    'deepseek': 'deepseek-v4-flash',
    'moonshot': 'moonshot-v1-8k',
    'qwen': 'qwen-plus',
    'glm': 'glm-4-plus',
    'ernie': 'ernie-4.0-8k',
    'doubao': 'doubao-pro-32k',
    'baichuan': 'baichuan4',
    'yi': 'yi-lightning',
    'siliconflow': 'deepseek-ai/DeepSeek-V3',
    'custom': '',
  };

  var PROVIDER_LABELS = {
    'openai': 'OpenAI',
    'anthropic': 'Anthropic',
    'azure': 'Azure',
    'ollama': 'Ollama',
    'lmstudio': 'LM Studio',
    'deepseek': 'DeepSeek',
    'moonshot': 'Moonshot',
    'qwen': '通义千问',
    'glm': '智谱 GLM',
    'ernie': '百度文心',
    'doubao': '字节豆包',
    'baichuan': '百川',
    'yi': '零一万物',
    'siliconflow': 'SiliconFlow',
    'custom': '',
  };

  var $providerSelect = $('#llm_provider_provider_hidden');
  var $endpointInput = $('#llm_provider_apiEndpoint');
  var $modelInput = $('#llm_provider_model');
  var $nameInput = $('#llm_provider_name');

  // ============================================================
  // Thinking mode options (DeepSeek only)
  // ============================================================
  var $thinkingSectionTitle = $('#thinking-section-title');
  var $thinkingOptions = $('.thinking-option');
  var $thinkingEnabled = $('#llm_provider_thinkingEnabled');
  var $reasoningEffortRow = $('#reasoning-effort-row');

  function updateThinkingVisibility(provider) {
    var show = provider === 'deepseek';
    $thinkingSectionTitle.toggle(show);
    $thinkingOptions.toggle(show);
  }

  function updateReasoningEffortVisibility() {
    $reasoningEffortRow.toggle(!!($thinkingEnabled.is(':checked')));
  }

  $thinkingEnabled.on('change', updateReasoningEffortVisibility);

  if ($providerSelect.length) {
    // Store original endpoint value on page load (for edit mode)
    var originalEndpoint = $endpointInput.val();
    var originalModel = $modelInput.val();
    var originalName = $nameInput.val();

    $providerSelect.on('change', function () {
      var val = $(this).val();
      var endpoint = PROVIDER_ENDPOINTS[val] || '';

      if (endpoint) {
        $endpointInput.val(endpoint);
        $endpointInput.prop('readonly', true);
        $endpointInput.css({
          'background-color': '#f5f5f7',
          'color': '#86868b',
          'cursor': 'default',
        });
      } else {
        $endpointInput.prop('readonly', false);
        $endpointInput.css({
          'background-color': '',
          'color': '',
          'cursor': '',
        });
        // Only clear if it was previously auto-filled
        if (originalEndpoint && $endpointInput.val() === originalEndpoint) {
          // keep it
        } else if (!originalEndpoint) {
          $endpointInput.val('');
        }
      }

      // Auto-fill model hint if model field is empty
      var modelHint = PROVIDER_MODEL_HINTS[val] || '';
      if (modelHint && !originalModel) {
        $modelInput.val(modelHint);
      }

      // Auto-fill name if name field is empty
      var label = PROVIDER_LABELS[val] || '';
      if (label && !originalName) {
        $nameInput.val(label);
      }

      updateThinkingVisibility(val);
    });

    // Trigger on page load to set initial state
    if ($providerSelect.val()) {
      $providerSelect.trigger('change');
    }

    updateReasoningEffortVisibility();
  }

  // ============================================================
  // Test connection
  // ============================================================
  $('.btn-test-connection').on('click', function () {
    const $btn = $(this);
    const providerId = $btn.data('provider-id');
    const $card = $btn.closest('.llm-provider-card');
    const $status = $card.find('.test-status');

    $btn.prop('disabled', true).text('测试中...');
    $status.html('<i class="fa-solid fa-spinner fa-spin"></i>');

    $.post('/admin/platform/llm-config/provider/' + providerId + '/test')
      .done(function (res) {
        $status.html(
          '<span class="text-success">' +
          '<i class="fa-solid fa-check-circle"></i> ' +
          res.elapsed + 'ms' +
          '</span>'
        );
      })
      .fail(function (xhr) {
        var err = (xhr.responseJSON && xhr.responseJSON.error) || '连接失败';
        $status.html(
          '<span class="text-danger">' +
          '<i class="fa-solid fa-times-circle"></i> ' +
          err +
          '</span>'
        );
      })
      .always(function () {
        $btn.prop('disabled', false).text('测试连接');
      });
  });

  // ============================================================
  // Toggle enable/disable
  // ============================================================
  $('.provider-toggle').on('change', function () {
    var $checkbox = $(this);
    var $card = $checkbox.closest('.llm-provider-card');
    var providerId = $card.data('provider-id');

    $.post('/admin/platform/llm-config/provider/' + providerId + '/toggle')
      .fail(function () {
        $checkbox.prop('checked', !$checkbox.prop('checked'));
        var $card = $checkbox.closest('.llm-provider-card');
        $card.find('.test-status').html(
          '<span class="text-danger"><i class="fa-solid fa-times-circle"></i> 切换失败</span>'
        );
      });
  });
});
