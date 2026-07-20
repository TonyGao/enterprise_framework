$(document).ready(function () {
  function getUserInfoFromNode(node) {
    return {
      name: node.attr('data-name') || (node.text() || '').trim(),
      employeeNo: node.attr('data-employee-no') || '',
      department: node.attr('data-department') || '',
      position: node.attr('data-position') || '',
      company: node.attr('data-company') || '',
      email: node.attr('data-email') || '',
      mobile: node.attr('data-mobile') || '',
    };
  }

  function renderUserInfo(modal, info) {
    modal.find('[data-user-placeholder]').hide();
    modal.find('[data-user-detail]').show();
    modal.find('[data-user-field="name"]').text(info.name || '');
    modal.find('[data-user-field="employeeNo"]').text(info.employeeNo || '');
    modal.find('[data-user-field="department"]').text(info.department || '');
    modal.find('[data-user-field="position"]').text(info.position || '');
    modal.find('[data-user-field="company"]').text(info.company || '');
    modal.find('[data-user-field="email"]').text(info.email || '');
    modal.find('[data-user-field="mobile"]').text(info.mobile || '');
  }

  function renderUserPlaceholder(modal) {
    modal.find('[data-user-detail]').hide();
    modal.find('[data-user-placeholder]').show();
  }

  function hydrateRightPanel(modal, userInput) {
    var selectedId = userInput
      .find('.ef-user-selection-span ul a.ef-link')
      .first()
      .attr('id');

    if (!selectedId) {
      renderUserPlaceholder(modal);
      return;
    }

    var selectedNode = modal
      .find('.user-tree-wrapper .org-text-content.user[type="user"][id="' + selectedId + '"]')
      .first();

    if (!selectedNode.length) {
      renderUserPlaceholder(modal);
      return;
    }

    renderUserInfo(modal, getUserInfoFromNode(selectedNode));
  }

  function updateDetailBySelection(line) {
    var modal = line.closest('.ef-modal-container');
    var content = line.find('.org-text-content.user[type="user"]').first();
    if (!content.length) {
      return;
    }
    renderUserInfo(modal, getUserInfoFromNode(content));
  }

  function syncUserPopupSize(userInput, contentId) {
    var popup = $('#' + contentId);
    if (popup.length === 0) {
      return;
    }
    popup.css({
      width: userInput.outerWidth(),
      minWidth: userInput.outerWidth(),
    });
  }

  $('body').on('click', '.ef-user-view-search', function () {
    $(this).find('input:first').focus();
  });

  var userVar = 'v' + Math.random();
  window[userVar] = '';

  var flag = true;
  $('body').on('compositionstart', '.ef-user-view-input', function () {
    flag = false;
  });

  $('body').on('compositionend', '.ef-user-view-input', function () {
    flag = true;
  });

  // Search dropdown
  $('body').on(
    'input change',
    '.ef-user-view-input',
    _.debounce(function () {
      if (flag) {
        var v = $(this).val();
        var userInput;
        var contentId;
        if (window[userVar] != v && v != '') {
          var mode = $(this).attr('mode');
          userInput = $(this).parents('.ef-user-view-single.ef-user');
          contentId = userInput.attr('contentid');
          var companyId = userInput.attr('data-company-id');
          var searchUrl = userInput.attr('data-search-url') || '/api/admin/org/user/searchByKey';

          window[userVar] = v;
          var payload = { key: $(this).val() };
          if (companyId) {
            payload.companyId = companyId;
          }

          $.ajax({
            url: searchUrl,
            method: 'POST',
            dataType: 'json',
            data: JSON.stringify(payload),
            success: function (response) {
              var data = response.data;
              if (Array.isArray(data) && data.length !== 0) {
                var height = userInput.outerHeight();
                var top = userInput.position().top + height + 6;
                var left = userInput.position().left;
                var selectionUl = userInput.children().find('.ef-user-selection-span ul');

                syncUserPopupSize(userInput, contentId);
                $('#' + contentId).css({ left: left, top: top });
                $('.ef-trigger-popup.ef-trigger-position-bl').hide();
                var liHtml = '';

                var selectionIds = [];
                if (mode === 'multiple') {
                  selectionUl.find('a').each(function () {
                    selectionIds.push($(this).attr('id'));
                  });
                }

                var num = 0;
                $.each(data, function (idx, item) {
                  var randomId = item.id;
                  if (!selectionIds.includes(randomId.toString())) {
                    liHtml += '<li id="' + randomId + '" class="ef-user-option"><span class="ef-user-option-content">' + item.displayName + '</span></li>';
                    num += 1;
                  }
                });

                if (liHtml !== '') {
                  $('#' + contentId + ' .ef-user-dropdown-list').html(liHtml);
                  $('#' + contentId).show();
                }

                $('.ef-user-option').not('.selected').on('click', function () {
                  userInput.attr('chosen', 'true');
                  var id = $(this).attr('id');
                  var content = $(this).children('.ef-user-option-content').text();
                  var template = '<li class="ef-user-selection-li">' +
                    '<div class="ef-user-selection-li-content">' +
                    '<a href="link" class="ef-link" id="' + id + '">' + content + '</a>' +
                    '<span class="ef-user-view-suffix close-chose-user" style="display: none;">' +
                    '<span class="ef-user-view-icon"><i class="fa-regular fa-circle-xmark"></i></span></span>' +
                    '</div></li>';

                  if (mode === 'single') {
                    selectionUl.html(template);
                    $('#' + contentId).hide();
                    var input = $('[contentId="' + contentId + '"]').find(
                      '.ef-user-selection-container input.ef-user-view-input'
                    );
                    input.attr('chose', 'true');
                    input.val('');
                    input.hide();
                    var inputSubmit = input.siblings('input[type="hidden"]');
                    inputSubmit.val(id);
                  }

                  if (mode === 'multiple') {
                    selectionUl.append(template);
                    var liHeight = selectionUl.find('li').last().outerHeight() + 6;
                    var currentTop = parseFloat($('#' + contentId).css('top'), 10);
                    $('#' + contentId).css('top', currentTop + liHeight);
                    $(this).addClass('selected');
                    $(this).off();
                    if (num === 1) {
                      $('#' + contentId).hide();
                    }
                    updateMultiUserHiddenInput(userInput);
                  }

                  refreshSelectionHover();
                });
              }
            },
          });
        }

        if (v === '') {
          $('#' + contentId).hide();
        }
        if (v === '') {
          window[userVar] = '';
        }
      }
    }, 200)
  );

  // Hide dropdown on outside click
  $(document).on('click', function (event) {
    if (
      $(event.target).closest('.ef-trigger-popup-wrapper, .ef-select-view-single').length > 0 ||
      $(event.target).children().find('.ef-user-selection-li-content').length > 0
    ) {
      var input = $('.ef-user-view-input');
      if (input.length > 0) {
        $.each(input, function (idx, ele) {
          if ($(ele).attr('chose') == 'true') {
            $(ele).hide();
          }
        });
      }
      input.val('');
    }
  });

  function refreshSelectionHover() {
    $('body').on('mouseenter', '.ef-user-selection-li', function () {
      $(this).find('.close-chose-user').css('display', 'inline-flex');
    });
    $('body').on('mouseleave', '.ef-user-selection-li', function () {
      $(this).find('.close-chose-user').css('display', 'none');
    });
  }

  function updateMultiUserHiddenInput(userInput) {
    var hiddenInput = userInput.closest('.ef-deparment-element').find('> input[type="hidden"]');
    if (!hiddenInput.length) return;
    var ids = [];
    userInput.find('.ef-user-selection-span a').each(function () {
      ids.push($(this).attr('id'));
    });
    hiddenInput.val(JSON.stringify(ids));
  }

  refreshSelectionHover();

  // Delete selected item
  $('body').on('click', '.close-chose-user', function (event) {
    event.stopPropagation();
    var $li = $(this).parents('.ef-user-selection-li');
    var userInput = $(this).closest('.ef-user');
    var mode = userInput.find('.ef-user-view-input').attr('mode');
    if (mode === 'single') {
      var input = $(this).parents('.ef-user-selection-container').find('input.ef-user-view-input');
      input.attr('chose', 'false');
      input.show();
      input.focus();
      input.siblings('input[type="hidden"]').val('');
    }
    $li.remove();
    if (mode === 'multiple') {
      updateMultiUserHiddenInput(userInput);
    }
  });

  // Click on selected item to re-show input
  $('body').on('click', '.ef-user-selection-li-content', function (event) {
    event.stopPropagation();
    var input = $(this).parents('.ef-user-selection-container').find('input.ef-user-view-input');
    input.show().focus();
  });

  // Tree arrow toggle
  $('body').on('click', '.user-tree-wrapper .sub-tree-content .arrow-icon', function (event) {
    event.stopPropagation();
    var parentItem = $(this).closest('.item-content');
    parentItem.nextAll('.tree-indent, .sub-tree-content').toggle(0);
    var icon = $(this).find('i');
    if (icon.hasClass('fa-caret-down')) {
      icon.removeClass('fa-caret-down').addClass('fa-caret-right');
    } else {
      icon.removeClass('fa-caret-right').addClass('fa-caret-down');
    }
  });

  // Tree line click => trigger radio
  $('body').on('click', '.user-tree-wrapper .user-select-line', function () {
    $(this).children('.ef-radio').trigger('click');
  });

  // Radio click => select user
  $('body').on('click', '.user-tree-wrapper .ef-radio', function (event) {
    var line = $(this).parent();
    var content = line.find('.org-text-content.user[type="user"]').first();
    if (!content.length) return;
    var id = content.attr('id');
    var displayName = content.attr('data-name') || content.text().trim();
    var department = content.attr('data-department') || '';
    var employeeNo = content.attr('data-employee-no') || '';
    var displayText = displayName + '（' + department + ' · ' + employeeNo + '）';
    var button = line.parents('.ef-modal').find('.confirmUser[type="button"]');
    button.attr('choseId', id);
    button.attr('data-display-text', displayText);
    updateDetailBySelection(line);
    event.stopPropagation();
  });

  // Click on user text => trigger radio
  $('body').on('click', '.user-tree-wrapper .org-text-content.user[type="user"]', function (event) {
    event.stopPropagation();
    var line = $(this).closest('.user-select-line');
    var radio = line.find('.ef-radio').first();
    if (radio.length) {
      radio.trigger('click');
    } else {
      updateDetailBySelection(line);
    }
  });

  // Close modal
  $('body').on('click', '.cancelUser', function () {
    var inputid = $(this).attr('inputid');
    $(".ef-modal-container[inputid='" + inputid + "']").hide();
  });

  // Show user modal
  $('body').on('click', '.show-user-modal', async function () {
    var inputid = $(this).parent().attr('id');
    var userInput = $(this).parent();
    var companyId = userInput.attr('data-company-id');
    var mode = userInput.find('.ef-user-view-input').attr('mode');

    var modal = $(".ef-modal-container[inputid='" + inputid + "']");
    if (modal.length > 0) {
      modal.show();
      renderUserPlaceholder(modal);
    } else {
      var modalWindow = '\
    <div class="ef-modal-container" style="z-index: 1001;" inputid="' + inputid + '">\
    <div class="ef-modal-mask"></div>\
    <div class="ef-modal-wrapper ef-modal-wrapper-align-center">\
      <div class="ef-modal" style="width: 784px">\
        <div class="ef-modal-header">\
          <div class="ef-modal-title ef-modal-title-align-left">\
            ' + (mode == 'single' ? '单用户选择' : '多用户选择') + '\
          </div>\
          <div tabindex="-1" role="button" aria-label="Close" class="ef-modal-close-btn cancelUser" inputid="' + inputid + '">\
            <span class="ef-icon-hover">\
              <svg viewbox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="currentColor" class="ef-icon ef-icon-close" stroke-width="4" stroke-linecap="butt" stroke-linejoin="miter">\
                <path d="M9.857 9.858 24 24m0 0 14.142 14.142M24 24 38.142 9.858M24 24 9.857 38.142"></path>\
              </svg>\
            </span>\
          </div>\
        </div>\
        <div class="ef-modal-body">\
          <div class="user-modal-body">\
            <div class="left-tree-wrapper">\
              <div class="search-user-wrapper">\
                <span class="ef-input-wrapper">\
                  <input class="ef-input ef-input-size-mini text user-tree-search" type="text" clearable="true" placeholder="请输入姓名或拼音">\
                  <span class="ef-input-suffix">\
                    <i class="fa-solid fa-magnifying-glass"></i>\
                  </span>\
                </span>\
              </div>\
              <div class="user-tree-wrapper common-tree-wrapper single-user">\
              </div>\
            </div>\
            <div class="right-user-wrapper">\
              <div class="user-placeholder" data-user-placeholder>\
                <div class="user-placeholder-icon">\
                  <i class="fa-regular fa-user"></i>\
                </div>\
                <div class="user-placeholder-text">请选择左侧用户以查看详情</div>\
              </div>\
              <div data-user-detail style="display: none;">\
                <div class="ef-row ef-row-align-start ef-row-justify-start ef-form-item ef-form-item-layout-horizontal">\
                  <div class="ef-col ef-col-6 ef-form-item-label-col">\
                    <label class="ef-form-item-label">姓名</label>\
                  </div>\
                  <div class="ef-col ef-col-18 ef-form-item-wrapper-col">\
                    <div class="ef-form-item-content-wrapper">\
                      <div class="ef-form-item-content ef-form-item-content-flex" data-user-field="name"></div>\
                    </div>\
                  </div>\
                </div>\
                <div class="ef-row ef-row-align-start ef-row-justify-start ef-form-item ef-form-item-layout-horizontal">\
                  <div class="ef-col ef-col-6 ef-form-item-label-col">\
                    <label class="ef-form-item-label">工号</label>\
                  </div>\
                  <div class="ef-col ef-col-18 ef-form-item-wrapper-col">\
                    <div class="ef-form-item-content-wrapper">\
                      <div class="ef-form-item-content ef-form-item-content-flex" data-user-field="employeeNo"></div>\
                    </div>\
                  </div>\
                </div>\
                <div class="ef-row ef-row-align-start ef-row-justify-start ef-form-item ef-form-item-layout-horizontal">\
                  <div class="ef-col ef-col-6 ef-form-item-label-col">\
                    <label class="ef-form-item-label">部门</label>\
                  </div>\
                  <div class="ef-col ef-col-18 ef-form-item-wrapper-col">\
                    <div class="ef-form-item-content-wrapper">\
                      <div class="ef-form-item-content ef-form-item-content-flex" data-user-field="department"></div>\
                    </div>\
                  </div>\
                </div>\
                <div class="ef-row ef-row-align-start ef-row-justify-start ef-form-item ef-form-item-layout-horizontal">\
                  <div class="ef-col ef-col-6 ef-form-item-label-col">\
                    <label class="ef-form-item-label">职位</label>\
                  </div>\
                  <div class="ef-col ef-col-18 ef-form-item-wrapper-col">\
                    <div class="ef-form-item-content-wrapper">\
                      <div class="ef-form-item-content ef-form-item-content-flex" data-user-field="position"></div>\
                    </div>\
                  </div>\
                </div>\
                <div class="ef-row ef-row-align-start ef-row-justify-start ef-form-item ef-form-item-layout-horizontal">\
                  <div class="ef-col ef-col-6 ef-form-item-label-col">\
                    <label class="ef-form-item-label">公司</label>\
                  </div>\
                  <div class="ef-col ef-col-18 ef-form-item-wrapper-col">\
                    <div class="ef-form-item-content-wrapper">\
                      <div class="ef-form-item-content ef-form-item-content-flex" data-user-field="company"></div>\
                    </div>\
                  </div>\
                </div>\
                <div class="ef-row ef-row-align-start ef-row-justify-start ef-form-item ef-form-item-layout-horizontal">\
                  <div class="ef-col ef-col-6 ef-form-item-label-col">\
                    <label class="ef-form-item-label">邮箱</label>\
                  </div>\
                  <div class="ef-col ef-col-18 ef-form-item-wrapper-col">\
                    <div class="ef-form-item-content-wrapper">\
                      <div class="ef-form-item-content ef-form-item-content-flex" data-user-field="email"></div>\
                    </div>\
                  </div>\
                </div>\
                <div class="ef-row ef-row-align-start ef-row-justify-start ef-form-item ef-form-item-layout-horizontal">\
                  <div class="ef-col ef-col-6 ef-form-item-label-col">\
                    <label class="ef-form-item-label">手机</label>\
                  </div>\
                  <div class="ef-col ef-col-18 ef-form-item-wrapper-col">\
                    <div class="ef-form-item-content-wrapper">\
                      <div class="ef-form-item-content ef-form-item-content-flex" data-user-field="mobile"></div>\
                    </div>\
                  </div>\
                </div>\
              </div>\
            </div>\
          </div>\
        </div>\
        <div class="ef-modal-footer">\
          <button class="btn secondary small cancelUser" type="button" inputid="' + inputid + '">取消</button>\
          <button class="btn primary small confirmUser" type="button" mode="' + mode + '" inputid="' + inputid + '">确定</button>\
        </div>\
      </div>\
    </div>\
    </div>';

      $('body').append(modalWindow);

      modal = $(".ef-modal-container[inputid='" + inputid + "']");
      modal.show();
      renderUserPlaceholder(modal);

      var requestUrl = userInput.attr('data-modal-url') || '/admin/org/user/singleSelect';
      var cacheKey = requestUrl + (companyId ? '.' + companyId : '');
      var requestData = companyId ? { companyId: companyId } : {};

      var singleUserCache = await Common.getCache(cacheKey);
      if (singleUserCache === null) {
        $.ajax({
          url: requestUrl,
          method: 'GET',
          data: requestData,
          dataType: 'html',
          success: async function (data) {
            await Common.setCache(cacheKey, data);
            modal.find('.user-tree-wrapper').html(data);
            hydrateRightPanel(modal, userInput);
          },
        });
      } else {
        modal.find('.user-tree-wrapper').html(singleUserCache);
        hydrateRightPanel(modal, userInput);
      }
    }
  });

  // Tree search (pinyin + name)
  $('body').on(
    'input',
    '.user-tree-search',
    _.debounce(function () {
      var keyword = ($(this).val() || '').trim().toLowerCase();
      var treeWrapper = $(this).closest('.left-tree-wrapper').find('.user-tree-wrapper');

      if (keyword === '') {
        treeWrapper.find('.user-select-line').show();
        treeWrapper.find('.sub-tree-content').show();
        treeWrapper.find('.tree-indent').show();
        return;
      }

      // Hide all user lines first
      treeWrapper.find('.user-select-line').hide();
      treeWrapper.find('.sub-tree-content').show();
      treeWrapper.find('.tree-indent').show();

      // Show matching users
      treeWrapper.find('.org-text-content.user[type="user"]').each(function () {
        var name = ($(this).attr('data-name') || $(this).text() || '').trim();
        var nameLower = name.toLowerCase();
        var pinyin = '';
        if (typeof Pinyin !== 'undefined') {
          pinyin = Pinyin.convertToPinyin(name, '', true).toLowerCase();
        }

        if (nameLower.indexOf(keyword) !== -1 || pinyin.indexOf(keyword) !== -1) {
          var line = $(this).closest('.user-select-line');
          line.show();
          // Expand all parent tree nodes
          line.parents('.sub-tree-content').each(function () {
            $(this).show();
            $(this).prev('.tree-indent').show();
            var arrow = $(this).find('.arrow-icon i').first();
            if (arrow.length && arrow.hasClass('fa-caret-right')) {
              arrow.removeClass('fa-caret-right').addClass('fa-caret-down');
            }
          });
        }
      });
    }, 200)
  );

  // Confirm selection
  $('body').on('click', "[type='button'].confirmUser", function () {
    var inputid = $(this).attr('inputid');
    var choseId = $(this).attr('choseId');
    var mode = $(this).attr('mode');
    var displayText = $(this).attr('data-display-text') || '';
    var modal = $(this).parents(".ef-modal-container[inputid='" + inputid + "']");
    var userInput = $('#' + inputid);
    var selectionUl = userInput.find('.ef-user-selection-span ul');
    var template = '<li class="ef-user-selection-li">' +
      '<div class="ef-user-selection-li-content">' +
      '<a href="link" class="ef-link" id="' + choseId + '">' + displayText + '</a>' +
      '<span class="ef-user-view-suffix close-chose-user" style="display: none;">' +
      '<span class="ef-user-view-icon"><i class="fa-regular fa-circle-xmark"></i></span></span>' +
      '</div></li>';

    if (mode === 'single') {
      selectionUl.html(template);
      modal.hide();
      var input = userInput.find('.ef-user-selection-container input.ef-user-view-input');
      input.attr('chose', 'true');
      input.val('');
      input.hide();
      input.siblings('input[type="hidden"]').val(choseId);
    }

    if (mode === 'multiple') {
      if (choseId) {
        selectionUl.append(template);
      }
    }

    refreshSelectionHover();
  });

  // ── 键盘导航：上下箭头 + 回车选中（user 下拉搜索结果）──
  $(document).on('keydown', function (e) {
    const key = e.key;
    if (key !== 'ArrowDown' && key !== 'ArrowUp' && key !== 'Enter' && key !== 'Escape') return;

    // 找到当前打开的 user 面板（含 .ef-user-option 的可见面板）
    const panel = $('.ef-trigger-popup.ef-trigger-position-bl:visible').filter(function () {
      return $(this).find('.ef-user-option').length > 0;
    }).first();

    if (!panel.length) return;

    e.preventDefault();

    if (key === 'Escape') {
      panel.hide();
      return;
    }

    const options = panel.find('.ef-user-option:visible').not('.selected');
    if (!options.length) return;

    if (key === 'Enter') {
      const active = options.filter('.ef-user-option-active').first();
      if (active.length) active.trigger('click');
      return;
    }

    const currentIdx = options.index(options.filter('.ef-user-option-active'));
    let nextIdx;
    if (key === 'ArrowDown') {
      nextIdx = currentIdx < options.length - 1 ? currentIdx + 1 : 0;
    } else {
      nextIdx = currentIdx > 0 ? currentIdx - 1 : options.length - 1;
    }
    options.removeClass('ef-user-option-active');
    const nextOption = options.eq(nextIdx);
    nextOption.addClass('ef-user-option-active');

    // 滚动到可见区域
    const list = panel.find('.ef-user-dropdown-list');
    if (list.length) {
      const listEl = list[0];
      const optionEl = nextOption[0];
      if (optionEl.offsetTop < listEl.scrollTop) {
        listEl.scrollTop = optionEl.offsetTop;
      } else if (optionEl.offsetTop + optionEl.offsetHeight > listEl.scrollTop + listEl.clientHeight) {
        listEl.scrollTop = optionEl.offsetTop + optionEl.offsetHeight - listEl.clientHeight;
      }
    }
  });
});
