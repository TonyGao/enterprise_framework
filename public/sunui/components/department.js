$(document).ready(function () {
  function getDepartmentInfoFromNode(node) {
    const text = (node.text() || '').trim();
    const path = node.attr('path') || '';
    const pathParts = path ? path.split('/') : [];

    let company = node.attr('data-company-name') || '';
    if (!company && pathParts.length >= 2) {
      company = pathParts[1];
    }

    let parent = node.attr('data-parent-name') || '';
    if (!parent && pathParts.length >= 2) {
      parent = pathParts[pathParts.length - 2];
    }

    return {
      name: text,
      alias: node.attr('data-alias') || text,
      company: company,
      owner: node.attr('data-owner-name') || '',
      parent: parent === text ? '' : parent,
    };
  }

  function renderDepartmentInfo(modal, info) {
    modal.find('[data-department-placeholder]').hide();
    modal.find('[data-department-detail]').show();
    modal.find('[data-department-field="name"]').text(info.name || '');
    modal.find('[data-department-field="alias"]').text(info.alias || '');
    // Prefer the company name stored when opening modal (matches user's selection)
    const companyScope = (modal.attr('data-company-scope') || '').trim();
    modal.find('[data-department-field="company"]').text(companyScope || info.company || '');
    modal.find('[data-department-field="owner"]').text(info.owner || '');
    modal.find('[data-department-field="parent"]').text(info.parent || '');
  }

  function renderDepartmentPlaceholder(modal) {
    modal.find('[data-department-detail]').hide();
    modal.find('[data-department-placeholder]').show();
  }

  function hydrateRightPanel(modal, departmentInput) {
    const selectedId = departmentInput
      .find('.ef-department-selection-span ul a.ef-link')
      .first()
      .attr('id');

    if (!selectedId) {
      renderDepartmentPlaceholder(modal);
      return;
    }

    const selectedNode = modal
      .find('.department-tree-wrapper .org-text-content.department[type="department"][id="' + selectedId + '"]')
      .first();

    if (!selectedNode.length) {
      renderDepartmentPlaceholder(modal);
      return;
    }

    renderDepartmentInfo(modal, getDepartmentInfoFromNode(selectedNode));
  }

  function updateDetailBySelection(line) {
    const modal = line.closest('.ef-modal-container');
    const content = line.find('.org-text-content.department[type="department"]').first();
    if (!content.length) {
      return;
    }
    renderDepartmentInfo(modal, getDepartmentInfoFromNode(content));
  }

  function normalizePathWithCompanyScope(path, companyScope) {
    if (!path || !companyScope) {
      return path || '';
    }

    const parts = path.split('/').filter(Boolean);
    if (parts.length < 2) {
      return path;
    }

    // Keep group/department hierarchy, only replace the company segment.
    parts[1] = companyScope;
    return parts.join('/');
  }

  function syncDepartmentPopupSize(departmentInput, contentId) {
    const popup = $('#' + contentId);
    if (popup.length === 0) {
      return;
    }

    popup.css({
      width: departmentInput.outerWidth(),
      minWidth: departmentInput.outerWidth(),
    });
  }

  $('body').on('click', '.ef-department-view-search', function () {
    $(this).find('input:first').focus();
  });

  var departmentVar = 'v' + Math.random();
  window[departmentVar] = '';

  /**
   * flag 用来标记是否为中文输入完毕
   */
  let flag = true;
  $('body').on('compositionstart', '.ef-department-view-input', function () {
    flag = false;
  });

  $('body').on('compositionend', '.ef-department-view-input', function () {
    flag = true;
  });

  $('body').on(
    'input change',
    '.ef-department-view-input',
    _.debounce(function () {
      if (flag) {
        let v = $(this).val();
        let departmentInput;
        let contentId;
        // 避免重复或空值执行
        if (window[departmentVar] != v && v != '') {
          let mode = $(this).attr('mode'); // 模式分为单选 single，和多选 mutiple
          departmentInput = $(this).parents(
            '.ef-department-view-single.ef-department'
          );
          contentId = departmentInput.attr('contentid');
          let companyId = departmentInput.attr('data-company-id');
          // Support custom API URL from data attribute, fallback to default
          let searchUrl = departmentInput.attr('data-search-url') || '/api/admin/org/department/searchByKey';

          window[departmentVar] = v;
          let payload = {
            key: $(this).val(),
          };

          if (companyId) {
            payload.companyId = companyId;
          }

          $.ajax({
            url: searchUrl,
            method: 'POST',
            dataType: 'json',
            data: JSON.stringify(payload),
            success: function (response) {
              let data = response.data;
              if (Array.isArray(data) && data.length !== 0) {
                let height = departmentInput.outerHeight();
                let top = departmentInput.position().top + height + 6;
                let left = departmentInput.position().left;
                let selectionUl = departmentInput
                  .children()
                  .find('.ef-department-selection-span ul');
                syncDepartmentPopupSize(departmentInput, contentId);
                $('#' + contentId).css({
                  left: left,
                  top: top,
                });
                $('.ef-trigger-popup.ef-trigger-position-bl').hide();
                let liHtml = '';

                selectionIds = [];
                if (mode === 'multiple') {
                  let selDoms = selectionUl.find('a');
                  selDoms.each(function (idx, dom) {
                    let selId = $(dom).attr('id');
                    selectionIds.push(selId);
                  });
                }

                let num = 0;
                $.each(data, function (idx, item) {
                  let randomId = item.id;

                  // 如果已经选中了，就忽略，如果没选中过添加
                  if (!selectionIds.includes(randomId.toString())) {
                    let liTemplate = `<li id = "${randomId}" class="ef-department-option"><span class="ef-department-option-content">${item.displayName}</span></li>`;
                    liHtml += liTemplate;
                    num += 1;
                  }
                });

                if (liHtml !== '') {
                  $('#' + contentId + ' .ef-department-dropdown-list').html(
                    liHtml
                  ); // 添加下拉菜单内容
                  $('#' + contentId).show(); // 显示菜单
                }

                /**
                 * 增加菜单选择事件
                 * 基本逻辑：
                 * (1) 单选部门，单击选中部门，在上方显示选中的部门（如果已有值则替换），清空input的搜索内容，清空菜单，隐藏菜单
                 * (2) 多选部门，单击选中部门，在菜单中显示选中的状态（不可再单击），并不隐藏菜单，可以继续选其他待选项，在上方添加选中的部门。
                 *     如果待选择的部门只有一个，那选中之后自动隐藏菜单。
                 */
                $('.ef-department-option')
                  .not('.selected')
                  .on('click', function (event) {
                    departmentInput.attr('chosen', 'true');
                    let id = $(this).attr('id');
                    let content = $(this)
                      .children('.ef-department-option-content')
                      .text();
                    let template = `<li class="ef-department-selection-li">
                                  <div class="ef-department-selection-li-content">
                                    <a href="link" class="ef-link" id="${id}">${content}</a>
                                    <span class="ef-department-view-suffix close-chose-department" style="display: none;">
                                      <span class="ef-department-view-icon">
                                        <i class="fa-regular fa-circle-xmark"></i>
                                      </span>
                                    </span>
                                  </div>
                                </li>`;

                    if (mode === 'single') {
                      // 清空上边选中的部门
                      selectionUl.html(template);
                      $('#' + contentId).hide();
                      let input = $('[contentId="' + contentId + '"]').find(
                        '.ef-department-selection-container input.ef-department-view-input'
                      );
                      input.attr('chose', 'true');
                      input.val('');
                      input.hide();
                      let inputSubmit = input.siblings('input[type="hidden"]');
                      inputSubmit.val(id);
                    }

                    if (mode === 'multiple') {
                      // 在上边添加选中的部门
                      selectionUl.append(template);
                      let liHeight =
                        selectionUl.find('li').last().outerHeight() + 6;
                      let currentTop = parseFloat(
                        $('#' + contentId).css('top'),
                        10
                      );
                      let targetTop = currentTop + liHeight;
                      $('#' + contentId).css('top', targetTop);
                      $('.ef-trigger-popup-close-button').on(
                        'click',
                        function () {
                          $('#' + contentId).hide();
                        }
                      );

                      $(this).addClass('selected'); // 选中的蓝色底色
                      $(this).off();

                      if (num === 1) {
                        $('#' + contentId).hide();
                      }
                    }

                    refreshSelectionHover();
                    //departmentInput.find("input").val("");

                    // 删除选项
                    $('body').on(
                      'click',
                      '.close-chose-department',
                      function () {
                        if (mode === 'single') {
                          let input = $(this)
                            .parents('.ef-department-selection-container')
                            .find('input');
                          input.attr('chose', 'false');
                          input.show();
                          input.focus();
                        }
                        $(this).parents('.ef-department-selection-li').remove();
                      }
                    );
                  });
              }
            },
          });
        }

        if (v === '') {
          $('#' + contentId).hide();
        }

        if (v === '') {
          window[departmentVar] = '';
        }
      }
    }, 200)
  );

  //隐藏选项弹窗，隐藏上方有选定部门的输入框，这是公共行为
  $(document).on('click', function (event) {
    if (
      $(event.target).closest(
        '.ef-trigger-popup-wrapper, .ef-select-view-single'
      ).length > 0 ||
      $(event.target).children().find('.ef-department-selection-li-content')
        .length > 0
    ) {
      let input = $('.ef-department-view-input');
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
    $('body').on('mouseenter', '.ef-department-selection-li', function () {
      $(this).find('.close-chose-department').css('display', 'inline-flex');
    });

    $('body').on('mouseleave', '.ef-department-selection-li', function () {
      $(this).find('.close-chose-department').css('display', 'none');
    });
  }

  refreshSelectionHover();

  $('body').on(
    'click',
    '.department-tree-wrapper .department-select-line',
    function (event) {
      $(this).children('.ef-radio').trigger('click');
    }
  );

  $('body').on('click', '.department-tree-wrapper .ef-radio', function (event) {
    // 获取部门id值
    let line = $(this).parent();
    let content = line
      .children()
      .find('.org-text-content.department[type="department"]');
    let id = content.attr('id');
    let path = content.attr('path');
    // 给确定按钮赋值选中的部门id
    let button = line
      .parents('.ef-modal')
      .children()
      .find('.confirmDepartment[type="button"]');
    button.attr('choseId', id);
    button.attr('path', path);
    updateDetailBySelection(line);
    event.stopPropagation();
  });

  $('body').on(
    'click',
    '.department-tree-wrapper .org-text-content.department[type="department"]',
    function (event) {
      event.stopPropagation();
      const line = $(this).closest('.department-select-line');
      const radio = line.find('.ef-radio').first();
      if (radio.length) {
        radio.trigger('click');
      } else {
        updateDetailBySelection(line);
      }
    }
  );

  // 退出部门弹窗
  $('body').on('click', '.cancelDepartment', function () {
    let inputid = $(this).attr('inputid');
    $(".ef-modal-container[inputid='" + inputid + "']").hide();
  });

  // 显示部门弹窗
  $('body').on('click', '.show-department-modal', async function () {
    let inputid = $(this).parent().attr('id');
    let departmentInput = $(this).parent();
    let companyId = departmentInput.attr('data-company-id');
    let mode = $(this)
      .parent()
      .children()
      .find('.ef-department-view-input')
      .attr('mode');

    let companyName = departmentInput.attr('data-company-name') || '';

    // 若 department 组件标记了需要公司联动（data-company-id 属性存在但为空），则提示用户先选公司
    const requiresCompany = departmentInput.is('[data-company-id]');
    if (requiresCompany && !companyId) {
      const companySelectId = departmentInput.attr('data-company-select-id');
      if (companySelectId) {
        // 使用 jquery.validate 相同的 ef-select-error 样式提示关联的公司选择框
        const $companySelect = $('#' + companySelectId);
        $companySelect.next('.ef-select').addClass('ef-select-error');
        $companySelect[0] && $companySelect[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        // 用户选好公司后自动清除错误
        $companySelect.one('change', function () {
          $(this).next('.ef-select').removeClass('ef-select-error');
        });
      } else {
        // 降级：在 department 组件本身显示错误（无关联公司选择框时）
        departmentInput.addClass('ef-department-error');
        setTimeout(function () { departmentInput.removeClass('ef-department-error'); }, 2000);
      }
      return;
    }

    // 检查是否已经存在该 inputid 的模态弹窗容器
    let modal = $(".ef-modal-container[inputid='" + inputid + "']");
    if (modal.length > 0) {
      const prevCompanyId = modal.attr('data-company-id-scope') || '';
      const companyChanged = prevCompanyId !== (companyId || '');

      modal.attr('data-company-scope', companyName);
      modal.attr('data-company-id-scope', companyId || '');
      // 更新标题中的公司徽章
      const badge = modal.find('.dept-modal-company-badge');
      if (companyName) {
        if (badge.length) {
          badge.text(companyName);
        } else {
          modal.find('.ef-modal-title').append(' &middot; <span class="dept-modal-company-badge">' + companyName + '</span>');
        }
      } else {
        badge.prev().remove(); // 移除 " · "
        badge.remove();
      }

      modal.show();
      renderDepartmentPlaceholder(modal);

      // 公司变了则重新加载树
      if (companyChanged) {
        let requestUrl = departmentInput.attr('data-modal-url') || '/admin/org/department/singleSelect';
        let requestData = companyId ? { companyId: companyId } : {};
        let cacheKey = companyId
          ? 'org.singleDepartment.v4.' + companyId
          : 'org.singleDepartment.v4';
        modal.find('.department-tree-wrapper').html('');
        const cached = await Common.getCache(cacheKey);
        if (cached !== null) {
          modal.find('.department-tree-wrapper').html(cached);
        } else {
          $.ajax({
            url: requestUrl,
            method: 'GET',
            data: requestData,
            dataType: 'html',
            success: async function (data) {
              await Common.setCache(cacheKey, data);
              modal.find('.department-tree-wrapper').html(data);
            },
          });
        }
      }
    } else {
      let modalWindow = `
    <div class="ef-modal-container" style="z-index: 1001;" inputid="${inputid}">
    <div class="ef-modal-mask"></div>
    <div class="ef-modal-wrapper ef-modal-wrapper-align-center">
      <div class="ef-modal" style="width: 784px">
        <div class="ef-modal-header">
          <div class="ef-modal-title ef-modal-title-align-left">
            ${mode == 'single' ? '单部门选择' : '多部门选择'}${companyName ? ' &middot; <span class="dept-modal-company-badge">' + companyName + '</span>' : ''}
          </div>
          <div tabindex="-1" role="button" aria-label="Close" class="ef-modal-close-btn" inputid="${inputid}">
            <span class="ef-icon-hover">
              <svg viewbox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="currentColor" class="ef-icon ef-icon-close" stroke-width="4" stroke-linecap="butt" stroke-linejoin="miter">
                <path d="M9.857 9.858 24 24m0 0 14.142 14.142M24 24 38.142 9.858M24 24 9.857 38.142"></path>
              </svg>
            </span>
          </div>
        </div>
        <div class="ef-modal-body">
          <div class="department-modal-body">
            <div class="left-tree-wrapper">
              <div class="search-department-wrapper">
                <span class="ef-input-wrapper">
                  <input class="ef-input ef-input-size-mini text" type="text" clearable="true" placeholder="请输入部门名称">
                  <span class="ef-input-suffix">
                    <i class="fa-solid fa-magnifying-glass"></i>
                  </span>
                </span>
              </div>
              <div class="department-tree-wrapper common-tree-wrapper single-department">
                
              </div>
            </div>
            <div class="right-department-wrapper">
              <div class="department-placeholder" data-department-placeholder>
                <div class="department-placeholder-icon">
                  <i class="fa-regular fa-folder-open"></i>
                </div>
                <div class="department-placeholder-text">请选择左侧部门以查看详情</div>
              </div>
              <div id="department" data-department-detail style="display: none;">
                <div class="ef-row ef-row-align-start ef-row-justify-start ef-form-item ef-form-item-layout-horizontal">
                  <div class="ef-col ef-col-6 ef-form-item-label-col">
                    <label class="ef-form-item-label" for="department_name">部门全称</label>
                  </div>
                  <div class="ef-col ef-col-18 ef-form-item-wrapper-col">
                    <div class="ef-form-item-content-wrapper">
                      <div class="ef-form-item-content ef-form-item-content-flex" data-department-field="name"></div>
                    </div>
                  </div>
                </div>
                <div class="ef-row ef-row-align-start ef-row-justify-start ef-form-item ef-form-item-layout-horizontal">
                  <div class="ef-col ef-col-6 ef-form-item-label-col">
                    <label class="ef-form-item-label" for="department_alias">部门简称</label>
                  </div>
                  <div class="ef-col ef-col-18 ef-form-item-wrapper-col">
                    <div class="ef-form-item-content-wrapper">
                      <div class="ef-form-item-content ef-form-item-content-flex" data-department-field="alias"></div>
                    </div>
                  </div>
                </div>
                <div class="ef-row ef-row-align-start ef-row-justify-start ef-form-item ef-form-item-layout-horizontal">
                  <div class="ef-col ef-col-6 ef-form-item-label-col">
                    <label class="ef-form-item-label" for="department_company">所属公司</label>
                  </div>
                  <div class="ef-col ef-col-18 ef-form-item-wrapper-col">
                    <div class="ef-form-item-content-wrapper">
                      <div class="ef-form-item-content ef-form-item-content-flex" data-department-field="company"></div>
                    </div>
                  </div>
                </div>
                <div class="ef-row ef-row-align-start ef-row-justify-start ef-form-item ef-form-item-layout-horizontal">
                  <div class="ef-col ef-col-6 ef-form-item-label-col">
                    <label class="ef-form-item-label" for="department_owner">部门负责人</label>
                  </div>
                  <div class="ef-col ef-col-18 ef-form-item-wrapper-col">
                    <div class="ef-form-item-content-wrapper">
                      <div class="ef-form-item-content ef-form-item-content-flex" data-department-field="owner"></div>
                    </div>
                  </div>
                </div>
                <div class="ef-row ef-row-align-start ef-row-justify-start ef-form-item ef-form-item-layout-horizontal">
                  <div class="ef-col ef-col-6 ef-form-item-label-col">
                    <label class="ef-form-item-label" for="department_parent">上级部门</label>
                  </div>
                  <div class="ef-col ef-col-18 ef-form-item-wrapper-col">
                    <div class="ef-form-item-content-wrapper">
                      <div class="ef-form-item-content ef-form-item-content-flex" data-department-field="parent"></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="ef-modal-footer">
          <button class="btn secondary small cancelDepartment" type="button" inputid="${inputid}">取消</button>
          <button class="btn primary small confirmDepartment" type="button" mode="${mode}" inputid="${inputid}">确定</button>
        </div>
      </div>
    </div>
    </div>`;

      // 将模态弹窗插入到页面中
      $('body').append(modalWindow);

      let modal = $(".ef-modal-container[inputid='" + inputid + "']");
      modal.attr('data-company-scope', companyName);
      modal.attr('data-company-id-scope', companyId || '');
      modal.show();
      renderDepartmentPlaceholder(modal);

      // 构建缓存key和请求URL，考虑companyId
      let cacheKey = companyId
        ? 'org.singleDepartment.v4.' + companyId
        : 'org.singleDepartment.v4';
      // Support custom modal URL from data attribute, fallback to default
      let requestUrl = departmentInput.attr('data-modal-url') || '/admin/org/department/singleSelect';
      let requestData = companyId ? { companyId: companyId } : {};

      // 调用部门接口，如果已经调用过就不再调用了，而是直接使用缓存
      let singleDepCache = await Common.getCache(cacheKey);
      if (singleDepCache === null) {
        $.ajax({
          url: requestUrl,
          method: 'GET',
          data: requestData,
          dataType: 'html',
          success: async function (data) {
            await Common.setCache(cacheKey, data);
            modal.find('.department-tree-wrapper').html(data);
            hydrateRightPanel(modal, departmentInput);
          },
        });
      } else {
        modal.children().find('.department-tree-wrapper').html(singleDepCache);
        hydrateRightPanel(modal, departmentInput);
      }
    }
  });

  $('body').on('click', '.ef-modal-close-btn', function (event) {
    event.stopPropagation();
    let inputid = $(this).attr('inputid');
    $(".ef-modal-container[inputid='" + inputid + "']").hide();
  });

  $('body').on('click', "[type='button'].confirmDepartment", function () {
    let inputid = $(this).attr('inputid');
    let choseId = $(this).attr('choseId');
    let mode = $(this).attr('mode');
    let path = $(this).attr('path');
    let modal = $(this).parents(
      ".ef-modal-container[inputid='" + inputid + "']"
    );
    const companyScope = (modal.attr('data-company-scope') || '').trim();
    const displayPath = normalizePathWithCompanyScope(path, companyScope);
    let departmentInput = $('#' + inputid);
    let selectionUl = departmentInput
      .children()
      .find('.ef-department-selection-span ul');
    let template = `<li class="ef-department-selection-li">
    <div class="ef-department-selection-li-content">
      <a href="link" class="ef-link" id="${choseId}">${displayPath}</a>
      <span class="ef-department-view-suffix close-chose-department" style="display: none;">
        <span class="ef-department-view-icon">
          <i class="fa-regular fa-circle-xmark"></i>
        </span>
      </span>
    </div>
    </li>`;

    if (mode === 'single') {
      selectionUl.html(template);
      modal.hide();
      let input = departmentInput.find(
        '.ef-department-selection-container input'
      );
      input.attr('chose', 'true');
      input.val('');
      input.hide();
    }

    refreshSelectionHover();
  });

  $('body').on(
    'click',
    '.ef-department-selection-li-content',
    function (event) {
      event.stopPropagation();
      let input = $(this)
        .parents('.ef-department-selection-container')
        .find('input');
      input.show().focus();
    }
  );

  // 删除选项
  $('body').on('click', '.close-chose-department', function (event) {
    event.stopPropagation();
    let input = $(this)
      .parents('.ef-department-selection-container')
      .find('input');
    input.attr('chose', 'false');
    input.show();
    input.focus();
    $(this).parents('.ef-department-selection-li').remove();
  });

  // Auto-bind company→department linkage.
  // Any department span with data-company-select-id="<hidden-input-id>" gets
  // data-company-id / data-company-name synced automatically whenever the
  // referenced company select changes — no per-template JS needed.
  $('[data-company-select-id]').each(function () {
    const $deptSpan = $(this);
    const $companyHidden = $('#' + $deptSpan.attr('data-company-select-id'));
    if (!$companyHidden.length) return;

    // Initial sync: keep current department selection, only sync company scope.
    let initialCompanyName = '';
    if ($companyHidden.is('select')) {
      initialCompanyName = $companyHidden.find(':selected').text().trim();
    } else {
      initialCompanyName = $companyHidden.next('.ef-select').find('.ef-select-view-value').text().trim();
    }
    $deptSpan
      .attr('data-company-id', $companyHidden.val() || '')
      .attr('data-company-name', initialCompanyName || '');

    $companyHidden.on('change.deptSync', function () {
      const companyId = $(this).val();
      let companyName = '';
      if ($(this).is('select')) {
        companyName = $(this).find(':selected').text().trim();
      } else {
        companyName = $(this).next('.ef-select').find('.ef-select-view-value').text().trim();
      }
      $deptSpan
        .attr('data-company-id', companyId || '')
        .attr('data-company-name', companyName || '')
        .attr('chosen', 'false');
      $deptSpan.find('.ef-department-selection-span ul').html('');
      $deptSpan.find('.ef-department-view-input').attr('chose', 'false').show().val('');
      $deptSpan.closest('.ef-deparment-element').find('input[type="hidden"]').val('');
    });
  });
});
