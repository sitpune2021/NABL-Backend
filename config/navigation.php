<?php

return [
  [
    "key"=> "home",
    "path"=> "/home",
    "title"=> "Home",
    "translateKey"=> "nav.home",
    "icon"=> "home",
    "type"=> "item",
    "for"=> "both",
    "authority"=> [],
    "subMenu"=> []
  ],
  [
    "key"=> "masters",
    "path"=> "",
    "title"=> "Masters",
    "translateKey"=> "nav.masters",
    "icon"=> "concepts",
    "type"=> "title",
    "for"=> "both",
    "authority"=> [],
    "meta"=> [],
    "subMenu"=> [
      [
        "key"=> "masters.category.list",
        "path"=> "/masters/category/list",
        "title"=> "Category",
        "translateKey"=> "nav.mastersCategory.list",
        "icon"=> "documentation",
        "type"=> "item",
        "for"=> "both",
        "authority"=> [],
        "meta"=> [
          "description"=> [
            "translateKey"=> "nav.mastersCategory.listDesc",
            "label"=> "AI-powered chat systems"
          ]
        ],
        "subMenu"=> []
      ],
      [
        "key"=> "masters.subcategory.list",
        "path"=> "/masters/subcategory/list",
        "title"=> "Sub Category",
        "translateKey"=> "nav.mastersSubCategory.list",
        "icon"=> "products",
        "type"=> "item",
        "for"=> "both",
        "authority"=> [],
        "meta"=> [
          "description"=> [
            "translateKey"=> "nav.mastersSubCategory.listDesc",
            "label"=> "AI-powered chat systems"
          ]
        ],
        "subMenu"=> []
      ],
      [
        "key"=> "masters.department.list",
        "path"=> "/masters/department/list",
        "title"=> "Department",
        "translateKey"=> "nav.mastersDepartment.list",
        "icon"=> "utilsDoc",
        "type"=> "item",
        "for"=> "both",
        "authority"=> [],
        "meta"=> [
          "description"=> [
            "translateKey"=> "nav.mastersDepartment.listDesc",
            "label"=> "AI-powered chat systems"
          ]
        ],
        "subMenu"=> []
      ],
      [
        "key"=> "masters.unit.list",
        "path"=> "/masters/unit/list",
        "title"=> "Unit",
        "translateKey"=> "nav.mastersUnit.list",
        "icon"=> "uiFormsSelect",
        "type"=> "item",
        "for"=> "both",
        "authority"=> [],
        "meta"=> [
          "description"=> [
            "translateKey"=> "nav.mastersUnit.listDesc",
            "label"=> "AI-powered chat systems"
          ]
        ],
        "subMenu"=> []
      ],
      [
        "key"=> "masters.template.list",
        "path"=> "/masters/template/list",
        "title"=> "Template",
        "translateKey"=> "nav.mastersTemplate.list",
        "icon"=> "uiComponents",
        "type"=> "item",
        "for"=> "both",
        "authority"=> [],
        "meta"=> [
          "description"=> [
            "translateKey"=> "nav.mastersTemplate.listDesc",
            "label"=> "AI-powered chat systems"
          ]
        ],
        "subMenu"=> []
      ],
      [
        "key"=> "masters.document.list",
        "path"=> "/masters/document/list",
        "title"=> "Document",
        "translateKey"=> "nav.mastersDocument.list",
        "icon"=> "dataDisplay",
        "type"=> "item",
        "for"=> "both",
        "authority"=> [],
        "meta"=> [
          "description"=> [
            "translateKey"=> "nav.mastersDocument.listDesc",
            "label"=> "AI-powered chat systems"
          ]
        ],
        "subMenu"=> []
      ],
      [
        "key"=> "masters.zone.list",
        "path"=> "/masters/zone/list",
        "title"=> "Zone",
        "translateKey"=> "nav.mastersZone.list",
        "icon"=> "navigation",
        "type"=> "item",
        "for"=> "both",
        "authority"=> [],
        "meta"=> [
          "description"=> [
            "translateKey"=> "nav.mastersZone.listDesc",
            "label"=> "AI-powered chat systems"
          ]
        ],
        "subMenu"=> []
      ],
      [
        "key"=> "masters.cluster.list",
        "path"=> "/masters/cluster/list",
        "title"=> "Cluster",
        "translateKey"=> "nav.mastersCluster.list",
        "icon"=> "fileManager",
        "type"=> "item",
        "for"=> "both",
        "authority"=> [],
        "meta"=> [
          "description"=> [
            "translateKey"=> "nav.mastersCluster.listDesc",
            "label"=> "AI-powered chat systems"
          ]
        ],
        "subMenu"=> []
      ],
      [
        "key"=> "masters.location.list",
        "path"=> "/masters/location/list",
        "title"=> "Location",
        "translateKey"=> "nav.mastersLocation.list",
        "icon"=> "uiGraphMaps",
        "type"=> "item",
        "for"=> "both",
        "authority"=> [],
        "meta"=> [
          "description"=> [
            "translateKey"=> "nav.mastersLocation.listDesc",
            "label"=> "AI-powered chat systems"
          ]
        ],
        "subMenu"=> []
      ],
      [
        "key"=> "masters.instrument.list",
        "path"=> "/masters/instrument/list",
        "title"=> "Instrument",
        "translateKey"=> "nav.mastersInstrument.list",
        "icon"=> "documentation",
        "type"=> "item",
        "for"=> "both",
        "authority"=> [],
        "meta"=> [
          "description"=> [
            "translateKey"=> "nav.mastersInstrument.listDesc",
            "label"=> "AI-powered chat systems"
          ]
        ],
        "subMenu"=> []
      ]
    ]
  ],
  [
    "key"=> "clients",
    "path"=> "",
    "title"=> "Clients",
    "translateKey"=> "nav.clients",
    "icon"=> "concepts",
    "type"=> "title",
    "for"=> "master",
    "authority"=> [],
    "meta"=> [],
    "subMenu"=> [
      [
        "key"=> "clients.lab.list",
        "path"=> "/clients/lab/list",
        "title"=> "Lab",
        "translateKey"=> "nav.clientsLab.list",
        "icon"=> "sharedComponentDoc",
        "type"=> "item",
        "for"=> "master",
        "authority"=> [],
        "meta"=> [
          "description"=> [
            "translateKey"=> "nav.clientsLab.listDesc",
            "label"=> "AI-powered chat systems"
          ]
        ],
        "subMenu"=> []
      ],
      [
        "key"=> "clients.lab.assignments.list",
        "path"=> "/clients/lab-assignments/list",
        "title"=> "Lab Assignments",
        "translateKey"=> "nav.clientsLab.list",
        "icon"=> "sharedComponentDoc",
        "type"=> "item",
        "for"=> "master",
        "authority"=> [],
        "meta"=> [
          "description"=> [
            "translateKey"=> "nav.clientsLab.listDesc",
            "label"=> "AI-powered chat systems"
          ]
        ],
        "subMenu"=> []
      ]
    ]
  ],
  [
    "key"=> "settings",
    "path"=> "",
    "title"=> "Settings",
    "translateKey"=> "nav.settings",
    "icon"=> "concepts",
    "type"=> "title",
    "for"=> "both",
    "authority"=> [],
    "meta"=> [],
    "subMenu"=> [
      [
        "key"=> "settings.rolesPermission.list",
        "path"=> "/settings/roles-permission/list",
        "title"=> "Roles & Permission",
        "translateKey"=> "nav.settingsRoles.list",
        "icon"=> "accountRoleAndPermission",
        "type"=> "item",
        "for"=> "both",
        "authority"=> [],
        "meta"=> [
          "description"=> [
            "translateKey"=> "nav.settingsRoles.listDesc",
            "label"=> "AI-powered chat systems"
          ]
        ],
        "subMenu"=> []
      ],
      [
        "key"=> "settings.user.list",
        "path"=> "/settings/user/list",
        "title"=> "User",
        "translateKey"=> "nav.settingsUser.list",
        "icon"=> "account",
        "type"=> "item",
        "for"=> "both",
        "authority"=> [],
        "meta"=> [
          "description"=> [
            "translateKey"=> "nav.settingsUser.listDesc",
            "label"=> "AI-powered chat systems"
          ]
        ],
        "subMenu"=> []
      ],
      [
        "key"=> "settings.standard.list",
        "path"=> "/settings/standard/list",
        "title"=> "Standards",
        "translateKey"=> "nav.settingsClauses.list",
        "icon"=> "helpCeterEditArticle",
        "type"=> "item",
        "for"=> "master",
        "authority"=> [],
        "meta"=> [
          "description"=> [
            "translateKey"=> "nav.settingsClauses.listDesc",
            "label"=> "AI-powered chat systems"
          ]
        ],
        "subMenu"=> []
      ],
      [
        "key"=> "settings.account",
        "path"=> "/settings/account/profile",
        "title"=> "Account",
        "translateKey"=> "nav.settingsAccount",
        "icon"=> "account",
        "type"=> "item",
        "for"=> "both",
        "authority"=> [],
        "meta"=> [
          "description"=> [
            "translateKey"=> "nav.settingsAccount.listDesc",
            "label"=> "Manage profile and security"
          ]
        ],
        "subMenu"=> []
      ],
      [
        "key"=> "settings.menu.list",
        "path"=> "/settings/menu/list",
        "title"=> "Menu",
        "translateKey"=> "nav.settingsMenu.list",
        "icon"=> "groupMenu",
        "type"=> "item",
        "for"=> "both",
        "authority"=> [],
        "meta"=> [],
        "subMenu"=> []
      ]
    ]
  ]
];